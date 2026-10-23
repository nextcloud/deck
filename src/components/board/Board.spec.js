/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { beforeEach, describe, expect, it, jest } from '@jest/globals'
import { unsubscribe } from '@nextcloud/event-bus'
import Board from './Board.vue'
import { showError } from '../../helpers/errors.js'
import logger from '../../logger.js'
import { createSession } from '../../sessions.js'

jest.mock('@nextcloud/event-bus', () => ({ subscribe: jest.fn(), unsubscribe: jest.fn() }))
jest.mock('@nextcloud/vue', () => ({}), { virtual: true })
jest.mock('pinia', () => ({ mapActions: () => ({}), mapState: () => ({}) }))
jest.mock('vue3-smooth-dnd', () => ({}))
jest.mock('vue-material-design-icons/Check.vue', () => ({}))
jest.mock('../card/CardSidebar.vue', () => ({}))
jest.mock('../Controls.vue', () => ({}))
jest.mock('../icons/DeckIcon.vue', () => ({}))
jest.mock('../search/GlobalSearchResults.vue', () => ({}))
jest.mock('./GanttView.vue', () => ({}))
jest.mock('./Stack.vue', () => ({}))
jest.mock('../../helpers/errors.js', () => ({ showError: jest.fn() }))
jest.mock('../../logger.js', () => ({ __esModule: true, default: { error: jest.fn() } }))
jest.mock('../../sessions.js', () => ({ createSession: jest.fn() }))
jest.mock('../../stores/board.js', () => ({}))
jest.mock('../../stores/card.js', () => ({}))
jest.mock('../../stores/settings.js', () => ({}))
jest.mock('../../stores/stack.js', () => ({}))

function context() {
	return {
		id: 7,
		disposed: false,
		loadId: 0,
		sessionClosing: null,
		loadBoardById: jest.fn().mockResolvedValue(),
		loadStacks: jest.fn().mockResolvedValue(),
		loadArchivedStacks: jest.fn().mockResolvedValue(),
		session: { close: jest.fn().mockResolvedValue() },
		stopMouseDrag: jest.fn(),
		openCardModal: jest.fn(),
		closeSession() {
			return Board.methods.closeSession.call(this)
		},
	}
}

function deferred() {
	let resolve
	let reject
	const promise = new Promise((resolvePromise, rejectPromise) => {
		resolve = resolvePromise
		reject = rejectPromise
	})
	return { promise, resolve, reject }
}

beforeEach(() => {
	jest.clearAllMocks()
	createSession.mockReturnValue({ close: jest.fn().mockResolvedValue() })
})

describe('board widget lifecycle', () => {
	it('closes the session and removes listeners on unmount', async () => {
		const board = context()
		const session = board.session
		Board.beforeUnmount.call(board)
		await Promise.resolve()
		expect(board.disposed).toBe(true)
		expect(session.close).toHaveBeenCalledTimes(1)
		expect(board.stopMouseDrag).toHaveBeenCalledTimes(1)
		expect(unsubscribe).toHaveBeenCalledWith('deck:card:open-modal', board.openCardModal)
	})

	it('reports session cleanup failures', async () => {
		const board = context()
		const error = new Error('Session close failed')
		board.session.close.mockRejectedValue(error)
		Board.beforeUnmount.call(board)
		await new Promise(setImmediate)
		expect(logger.error).toHaveBeenCalledWith('Failed to close board session', { error })
	})

	it('does not load stacks or start a session after unmount during board loading', async () => {
		const board = context()
		const loading = Board.methods.fetchData.call(board)
		board.disposed = true
		await loading
		expect(board.loadStacks).not.toHaveBeenCalled()
		expect(createSession).not.toHaveBeenCalled()
	})

	it('does not start a session after unmount during stack loading', async () => {
		const board = context()
		board.loadStacks.mockImplementation(async () => {
			board.disposed = true
		})
		await Board.methods.fetchData.call(board)
		expect(createSession).not.toHaveBeenCalled()
	})

	it('does not restart a session after unmount while closing the previous session', async () => {
		const board = context()
		board.session.close.mockImplementation(async () => {
			board.disposed = true
		})
		await Board.methods.fetchData.call(board)
		expect(createSession).not.toHaveBeenCalled()
	})

	it('does not toggle archived content after unmount during archived-stack loading', async () => {
		const board = context()
		board.$route = { params: { cardId: '9' } }
		board.cardById = jest.fn()
		board.toggleShowArchived = jest.fn()
		board.loadArchivedStacks.mockImplementation(async () => {
			board.disposed = true
		})
		await Board.methods.fetchData.call(board)
		expect(board.toggleShowArchived).not.toHaveBeenCalled()
		expect(createSession).not.toHaveBeenCalled()
	})

	it('still starts a session after a successful board load', async () => {
		const board = context()
		await Board.methods.fetchData.call(board)
		expect(createSession).toHaveBeenCalledWith(7)
		expect(showError).not.toHaveBeenCalled()
	})

	it('does not leak sessions when reloads overlap while closing the previous session', async () => {
		const board = context()
		const previousSession = board.session
		const closing = deferred()
		previousSession.close.mockReturnValue(closing.promise)
		const first = Board.methods.fetchData.call(board)
		await new Promise(setImmediate)
		const second = Board.methods.fetchData.call(board)
		await new Promise(setImmediate)
		expect(createSession).not.toHaveBeenCalled()
		closing.resolve()
		await Promise.all([first, second])

		expect(previousSession.close).toHaveBeenCalledTimes(1)
		expect(createSession).toHaveBeenCalledTimes(1)
		const currentSession = board.session
		Board.beforeUnmount.call(board)
		await new Promise(setImmediate)
		expect(currentSession.close).toHaveBeenCalledTimes(1)
		expect(board.session).toBeNull()
	})

	it('shares an in-flight close between a reload and unmount', async () => {
		const board = context()
		const previousSession = board.session
		const closing = deferred()
		previousSession.close.mockReturnValue(closing.promise)
		const loading = Board.methods.fetchData.call(board)
		await new Promise(setImmediate)
		Board.beforeUnmount.call(board)
		closing.resolve()
		await loading

		expect(previousSession.close).toHaveBeenCalledTimes(1)
		expect(createSession).not.toHaveBeenCalled()
	})

	it('does not continue a superseded load after the board changes', async () => {
		const board = context()
		const firstBoard = deferred()
		board.loadBoardById.mockReturnValueOnce(firstBoard.promise)
		const first = Board.methods.fetchData.call(board)
		board.id = 8
		await Board.methods.fetchData.call(board)
		firstBoard.resolve()
		await first

		expect(board.loadStacks).toHaveBeenCalledTimes(1)
		expect(board.loadStacks).toHaveBeenCalledWith(8)
		expect(createSession).toHaveBeenCalledTimes(1)
		expect(createSession).toHaveBeenCalledWith(8)
	})

	it('keeps the loading indicator until the latest load finishes', async () => {
		const board = context()
		const firstBoard = deferred()
		const secondBoard = deferred()
		board.loadBoardById.mockReturnValueOnce(firstBoard.promise).mockReturnValueOnce(secondBoard.promise)
		const first = Board.methods.fetchData.call(board)
		const second = Board.methods.fetchData.call(board)
		firstBoard.resolve()
		await first
		expect(board.loading).toBe(true)
		secondBoard.resolve()
		await second
		expect(board.loading).toBe(false)
	})

	it('logs stale failures without interrupting the latest load', async () => {
		const board = context()
		const firstBoard = deferred()
		const secondBoard = deferred()
		const error = new Error('Previous board load failed')
		board.loadBoardById.mockReturnValueOnce(firstBoard.promise).mockReturnValueOnce(secondBoard.promise)
		const first = Board.methods.fetchData.call(board)
		const second = Board.methods.fetchData.call(board)
		firstBoard.reject(error)
		await first
		expect(logger.error).toHaveBeenCalledWith('Failed to load board', { error })
		expect(showError).not.toHaveBeenCalled()
		expect(board.loading).toBe(true)
		secondBoard.resolve()
		await second
	})

	it('reports close failures without starting a replacement session', async () => {
		const board = context()
		const error = new Error('Session close failed')
		board.session.close.mockRejectedValue(error)
		await Board.methods.fetchData.call(board)
		expect(showError).toHaveBeenCalledWith(error)
		expect(createSession).not.toHaveBeenCalled()
		expect(board.sessionClosing).toBeNull()
		expect(board.loading).toBe(false)
	})

	it('reports board loading failures', async () => {
		const board = context()
		const error = new Error('Board load failed')
		board.loadBoardById.mockRejectedValue(error)
		await Board.methods.fetchData.call(board)
		expect(showError).toHaveBeenCalledWith(error)
		expect(createSession).not.toHaveBeenCalled()
	})

	it('only opens cards belonging to this widget store', () => {
		const board = { cardById: (id) => id === 9 ? { id: 9 } : null, localModal: null }
		Board.methods.openCardModal.call(board, 8)
		expect(board.localModal).toBeNull()
		Board.methods.openCardModal.call(board, 9)
		expect(board.localModal).toBe(9)
	})
})
