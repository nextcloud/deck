/**
 * SPDX-FileCopyrightText: 2022 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { listen } from '@nextcloud/notify_push'
import { sessionApi } from './services/SessionApi.js'
import store from './store/main.js'
import axios from '@nextcloud/axios'

const SESSION_INTERVAL = 90 // in seconds

let hasPush = false

let syncRunning = false

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

hasPush = listen('deck_board_update', (name, body) => {
	// ignore update events which we have triggered ourselves
	if (isOurSessionToken(body._causingSessionToken)) return

	// only handle update events for the currently open board
	const currentBoardId = store.state.currentBoard?.id
	if (body.id !== currentBoardId) return

	store.dispatch('refreshBoard', currentBoardId)
})

listen('deck_card_update', (name, body) => {

	// ignore update events which we have triggered ourselves
	if (isOurSessionToken(body._causingSessionToken)) return

	// only handle update events for the currently open board
	const currentBoardId = store.state.currentBoard?.id
	if (body.boardId !== currentBoardId) return

	store.dispatch('loadStacks', currentBoardId)
})

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
 */
export function createSession(boardId) {

	if (!isNotifyPushEnabled()) {
		// return a dummy object
		return {
			async close() {},
		}
	}

	// let's try to make createSession() synchronous, so that
	// the component doesn't need to bother about the asynchronousness
	let tokenPromise
	let token
	const create = () => {
		tokenPromise = sessionApi.createSession(boardId).then(res => res.token)
		tokenPromise.then((t) => {
			token = t
			axios.defaults.headers['x-nc-deck-session'] = t
		})
	}
	create()

	const ensureSession = async () => {
		if (!tokenPromise) {
			create()
			return
		}

		if (syncRunning) {
			return
		}

		try {
			syncRunning = true
			await sessionApi.syncSession(boardId, await tokenPromise)
		} catch (err) {
			if (err.response.status === 404) {
				// session probably expired, let's
				// create a fresh session
				create()
			} else {
				console.error('Failed to sync deck session', err)
			}
		} finally {
			syncRunning = false
		}
	}

	// periodically notify the server that we are still here
	let interval = setInterval(ensureSession, SESSION_INTERVAL * 1000)

	// close session when tab gets hidden/inactive
	const visibilitychangeListener = () => {
		if (document.visibilityState === 'hidden' && token) {
			sessionApi.closeSessionViaBeacon(boardId, token)
			tokenPromise = null
			token = null
			delete axios.defaults.headers['x-nc-deck-session']

			// stop session refresh interval
			clearInterval(interval)
		} else {
			// tab is back in focus or was restored from the bfcache
			ensureSession()

			// we must assume that the websocket connection was
			// paused and we have missed updates in the meantime.
			store.dispatch('refreshBoard', store.state.currentBoard?.id)

			// restart session refresh interval
			clearInterval(interval)
			interval = setInterval(ensureSession, SESSION_INTERVAL * 1000)
		}
	}
	document.addEventListener('visibilitychange', visibilitychangeListener)

	return {
		async close() {
			clearInterval(interval)
			document.removeEventListener('visibilitychange', visibilitychangeListener)
			if (token) {
				await sessionApi.closeSession(boardId, token)
				tokenPromise = null
				token = null
				delete axios.defaults.headers['x-nc-deck-session']
			}
		},
	}
}
