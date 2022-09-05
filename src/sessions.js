/*
 * @copyright Copyright (c) 2022, chandi Langecker (git@chandi.it)
 *
 * @license GNU AGPL version 3 or any later version
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 */

import { listen } from '@nextcloud/notify_push'
import { sessionApi } from './services/SessionApi'
import store from './store/main'

const SESSION_INTERVAL = 25 // in seconds

let hasPush = false

hasPush = listen('deck_board_update', (name, body) => {
	triggerDeckReload(body.id)
})

/**
 * is the notify_push app active and can
 * provide us with real time updates?
 */
export function isNotifyPushEnabled() {
	return hasPush
}

/**
 * Triggers a reload of the deck, if the provided id
 * matches the current open deck
 *
 * @param triggeredBoardId
 */
export function triggerDeckReload(triggeredBoardId) {
	const currentBoardId = store.state.currentBoard?.id

	// only handle update events for the currently open board
	if (triggeredBoardId !== currentBoardId) return

	store.dispatch('refreshBoard', currentBoardId)
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
		})
	}
	create()

	const ensureSession = async () => {
		if (!tokenPromise) {
			create()
			return
		}
		try {
			await sessionApi.syncSession(boardId, await tokenPromise)
		} catch (err) {
			// session probably expired, let's
			// create a fresh session
			create()
		}
	}

	// periodically notify the server that we are still here
	const interval = setInterval(ensureSession, SESSION_INTERVAL * 1000)

	// close session when
	const visibilitychangeListener = () => {
		if (document.visibilityState === 'hidden') {
			sessionApi.closeSessionViaBeacon(boardId, token)
			tokenPromise = null
			token = null
		} else {
			// tab is back in focus or was restored from the bfcache
			ensureSession()

			// we must assume that the websocket connection was
			// paused and we have missed updates in the meantime.
			triggerDeckReload()
		}
	}
	document.addEventListener('visibilitychange', visibilitychangeListener)

	return {
		async close() {
			clearInterval(interval)
			document.removeEventListener('visibilitychange', visibilitychangeListener)
			await sessionApi.closeSession(boardId, await tokenPromise)
		},
	}
}
