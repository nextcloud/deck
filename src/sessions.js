/**
 * SPDX-FileCopyrightText: 2022 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import axios from '@nextcloud/axios'
import { listen } from '@nextcloud/notify_push'
import { showError } from './helpers/errors.js'
import logger from './logger.js'
import { sessionApi } from './services/SessionApi.js'
import { useBoardStore } from './stores/board.js'
import { useStackStore } from './stores/stack.js'

const SESSION_INTERVAL = 90 // in seconds

let hasPush = false

let store = null

/**
 * used to verify, whether an event is originated by ourselves
 *
 * @param token
 */
function isOurSessionToken(token) {
	if (axios.defaults.headers['x-nc-deck-session']
		&& axios.defaults.headers['x-nc-deck-session'].startsWith(token)) {
		return true
	} else {
		return false
	}
}

/**
 *
 * @param storeInstance
 */
export function initSessions() {
	store = useBoardStore()
	hasPush = listen('deck_board_update', (name, body) => {
		// ignore update events which we have triggered ourselves
		if (isOurSessionToken(body._causingSessionToken)) {
			return
		}

		// only handle update events for the currently open board
		const currentBoardId = store.currentBoard?.id
		if (body.id !== currentBoardId) {
			return
		}

		store.refreshBoard(currentBoardId)
	})

	listen('deck_card_update', (name, body) => {
		// ignore update events which we have triggered ourselves
		if (isOurSessionToken(body._causingSessionToken)) {
			return
		}

		// only handle update events for the currently open board
		const currentBoardId = store.currentBoard?.id
		if (body.boardId !== currentBoardId) {
			return
		}

		useStackStore().loadStacks(currentBoardId)
	})
}

/**
 * is the notify_push app active and can
 * provide us with real time updates?
 */
export function isNotifyPushEnabled() {
	return hasPush
}

/**
 *
 * @param boardId
 * @param options
 * @param options.pushEnabled
 * @param options.boardStore
 * @param options.globalHeader
 * @param options.onError
 */
export function createSession(boardId, {
	pushEnabled = isNotifyPushEnabled(),
	boardStore = store,
	globalHeader = true,
	onError = showError,
} = {}) {
	if (!boardId || !pushEnabled) {
		// return a dummy object
		return {
			async close() {},
		}
	}

	// let's try to make createSession() synchronous, so that
	// the component doesn't need to bother about the asynchronousness
	let tokenPromise
	let token
	let closed = false
	let syncRunning = false
	let generation = 0
	const clearHeader = () => {
		if (globalHeader && axios.defaults.headers['x-nc-deck-session'] === token) {
			delete axios.defaults.headers['x-nc-deck-session']
		}
	}
	const report = (error) => {
		logger.error('Failed to create deck session', { error })
		onError(error)
	}
	const create = () => {
		const currentGeneration = ++generation
		tokenPromise = sessionApi.createSession(boardId).then(async ({ token: t }) => {
			if (closed || currentGeneration !== generation) {
				await sessionApi.closeSession(boardId, t)
				return null
			}
			token = t
			if (globalHeader) {
				axios.defaults.headers['x-nc-deck-session'] = t
			}
			return t
		})
		tokenPromise.catch(report)
	}
	create()

	const ensureSession = async () => {
		if (closed) {
			return
		}
		if (!tokenPromise) {
			create()
			return
		}

		if (syncRunning) {
			return
		}

		try {
			syncRunning = true
			const currentToken = await tokenPromise
			if (!closed && currentToken) {
				await sessionApi.syncSession(boardId, currentToken)
			}
		} catch (err) {
			if (closed) {
				return
			}
			if (err.response?.status === 404) {
				// session probably expired, let's
				// create a fresh session
				create()
			} else {
				logger.error('Failed to sync deck session', { error: err })
				onError(err)
			}
		} finally {
			syncRunning = false
		}
	}

	// periodically notify the server that we are still here
	let interval = setInterval(ensureSession, SESSION_INTERVAL * 1000)

	// close session when tab gets hidden/inactive
	const visibilitychangeListener = () => {
		if (document.visibilityState === 'hidden') {
			generation++
			if (token) {
				sessionApi.closeSessionViaBeacon(boardId, token)
			}
			clearHeader()
			tokenPromise = null
			token = null

			// stop session refresh interval
			clearInterval(interval)
		} else {
			// tab is back in focus or was restored from the bfcache
			ensureSession()

			// we must assume that the websocket connection was
			// paused and we have missed updates in the meantime.
			if (boardStore?.currentBoard) {
				boardStore.refreshBoard(boardStore.currentBoard.id).catch((error) => {
					logger.error('Failed to refresh deck session board', { error })
					onError(error)
				})
			}

			// restart session refresh interval
			clearInterval(interval)
			interval = setInterval(ensureSession, SESSION_INTERVAL * 1000)
		}
	}
	document.addEventListener('visibilitychange', visibilitychangeListener)

	return {
		async close() {
			if (closed) {
				return
			}
			closed = true
			clearInterval(interval)
			document.removeEventListener('visibilitychange', visibilitychangeListener)
			await tokenPromise
			if (token) {
				clearHeader()
				await sessionApi.closeSession(boardId, token)
				tokenPromise = null
				token = null
			}
		},
	}
}
