/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { afterEach, beforeEach, expect, jest, test } from '@jest/globals'
import axios from '@nextcloud/axios'
import { sessionApi } from './services/SessionApi.js'
import { createSession, initSessions } from './sessions.js'

jest.mock('@nextcloud/axios', () => ({ defaults: { headers: {} } }), { virtual: true })
jest.mock('@nextcloud/notify_push', () => ({ listen: jest.fn().mockReturnValue(true) }), { virtual: true })
jest.mock('./logger.js', () => ({ error: jest.fn() }))
jest.mock('./helpers/errors.js', () => ({ showError: jest.fn() }))
jest.mock('./stores/board.js', () => ({ useBoardStore: () => ({ currentBoard: { id: 42 }, refreshBoard: jest.fn().mockResolvedValue() }) }))
jest.mock('./stores/stack.js', () => ({ useStackStore: () => ({ loadStacks: jest.fn() }) }))
jest.mock('./services/SessionApi.js', () => ({
	sessionApi: {
		createSession: jest.fn(),
		syncSession: jest.fn().mockResolvedValue(),
		closeSession: jest.fn().mockResolvedValue(),
		closeSessionViaBeacon: jest.fn(),
	},
}))

beforeEach(() => {
	jest.useFakeTimers()
	jest.clearAllMocks()
	axios.defaults.headers = {}
	sessionApi.createSession.mockResolvedValue({ token: 'session-token' })
	sessionApi.syncSession.mockResolvedValue()
	// Session policy only needs visibility events, not a browser DOM.
	globalThis.document = Object.assign(new EventTarget(), { visibilityState: 'visible' })
})
afterEach(() => {
	jest.useRealTimers()
	delete globalThis.document
})

test('legacy sessions still set and remove the global session header', async () => {
	initSessions()
	const session = createSession(42)
	await Promise.resolve()
	expect(axios.defaults.headers['x-nc-deck-session']).toBe('session-token')
	await session.close()
	expect(sessionApi.closeSession).toHaveBeenCalledWith(42, 'session-token')
	expect(axios.defaults.headers['x-nc-deck-session']).toBeUndefined()
	expect(jest.getTimerCount()).toBe(0)
})

test('embedded sessions never alter the existing global header', async () => {
	axios.defaults.headers['x-nc-deck-session'] = 'host-session'
	const session = createSession(42, { pushEnabled: true, globalHeader: false })
	await Promise.resolve()
	await session.close()
	expect(axios.defaults.headers['x-nc-deck-session']).toBe('host-session')
	expect(jest.getTimerCount()).toBe(0)
})

test('closing before creation resolves closes the late session', async () => {
	let resolve
	sessionApi.createSession.mockReturnValue(new Promise((done) => {
		resolve = done
	}))
	const session = createSession(42, { pushEnabled: true, globalHeader: false })
	const closed = session.close()
	resolve({ token: 'late-session' })
	await closed
	expect(sessionApi.closeSession).toHaveBeenCalledTimes(1)
	expect(sessionApi.closeSession).toHaveBeenCalledWith(42, 'late-session')
	expect(jest.getTimerCount()).toBe(0)
})

test('visibility refresh uses the supplied board store', async () => {
	const board = { currentBoard: { id: 43 }, refreshBoard: jest.fn().mockResolvedValue() }
	const session = createSession(43, { pushEnabled: true, globalHeader: false, boardStore: board })
	await Promise.resolve()
	document.dispatchEvent(new Event('visibilitychange'))
	expect(board.refreshBoard).toHaveBeenCalledWith(43)
	await session.close()
	document.dispatchEvent(new Event('visibilitychange'))
	expect(board.refreshBoard).toHaveBeenCalledTimes(1)
})

test('sessions without push perform no API calls', async () => {
	const session = createSession(42, { pushEnabled: false })
	await session.close()
	expect(sessionApi.createSession).not.toHaveBeenCalled()
	expect(jest.getTimerCount()).toBe(0)
})

test('hiding the page while creation is pending closes the late session', async () => {
	let resolve
	sessionApi.createSession.mockReturnValue(new Promise((done) => {
		resolve = done
	}))
	const session = createSession(42, { pushEnabled: true, globalHeader: false })
	Object.defineProperty(document, 'visibilityState', { configurable: true, value: 'hidden' })
	document.dispatchEvent(new Event('visibilitychange'))
	resolve({ token: 'hidden-session' })
	await Promise.resolve()
	await Promise.resolve()
	await session.close()
	expect(sessionApi.closeSession).toHaveBeenCalledWith(42, 'hidden-session')
	expect(jest.getTimerCount()).toBe(0)
})

test('a late failed sync cannot reopen a disposed session', async () => {
	let reject
	sessionApi.syncSession.mockReturnValue(new Promise((_, fail) => {
		reject = fail
	}))
	const session = createSession(42, { pushEnabled: true, globalHeader: false })
	await Promise.resolve()
	await jest.advanceTimersByTimeAsync(90000)
	expect(sessionApi.syncSession).toHaveBeenCalledTimes(1)
	await session.close()
	reject({ response: { status: 404 } })
	await Promise.resolve()
	await Promise.resolve()
	expect(sessionApi.createSession).toHaveBeenCalledTimes(1)
	expect(jest.getTimerCount()).toBe(0)
})
