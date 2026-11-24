/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { beforeEach, describe, expect, it, jest } from '@jest/globals'
import { useBoardStore } from './board.js'
import { useSettingsStore } from './settings.js'
import { useStackStore } from './stack.js'

// defineStore hands back the options, so an action runs on a plain object.
jest.mock('pinia', () => ({ defineStore: (id, options) => options }))
jest.mock('@nextcloud/axios', () => ({}), { virtual: true })
jest.mock('@nextcloud/initial-state', () => ({ loadState: jest.fn() }), { virtual: true })
jest.mock('@nextcloud/router', () => ({ generateUrl: jest.fn() }), { virtual: true })
// The client is created when board.js loads, so the mock hands out a lazy wrapper.
const mockLoadById = jest.fn()
jest.mock('../services/BoardApi.js', () => ({
	BoardApi: jest.fn(() => ({ loadById: (...args) => mockLoadById(...args) })),
}))
jest.mock('./card.js', () => ({ useCardStore: jest.fn() }))
jest.mock('./settings.js', () => ({ useSettingsStore: jest.fn() }))
jest.mock('./stack.js', () => ({ useStackStore: jest.fn() }))

const { setSwimlaneMode, setSwimlaneOrder, readSwimlaneSettings, refreshBoard, setCurrentBoard, setAssignableUsers } = useBoardStore.actions

function makeStore() {
	return {
		currentBoard: { id: 4, settings: { swimlaneMode: 'none' } },
		swimlaneLabelOrder: {},
		swimlaneUserOrder: {},
	}
}

// Store with the actions refreshBoard calls on itself.
function makeRefreshStore(boardId) {
	return {
		...makeStore(),
		currentBoard: { id: boardId, ETag: 'a', settings: { swimlaneMode: 'none' } },
		setCurrentBoard,
		setAssignableUsers,
		readSwimlaneSettings,
	}
}

// The board as the server answers a load.
function serverBoard(id, settings) {
	return { id, ETag: 'b', users: [], settings }
}

// A promise the test settles by hand, to interleave two writes.
function deferred() {
	let resolve, reject
	const promise = new Promise((res, rej) => {
		resolve = res
		reject = rej
	})
	return { promise, resolve, reject }
}

let setConfig

beforeEach(() => {
	jest.clearAllMocks()
	setConfig = jest.fn()
	useSettingsStore.mockReturnValue({ setConfig })
	useStackStore.mockReturnValue({ loadStacks: jest.fn() })
})

describe('setSwimlaneOrder', () => {
	it('rolls back when the server rejects the write', async () => {
		const store = makeStore()
		store.swimlaneLabelOrder[4] = [1, 2]
		setConfig.mockRejectedValue(new Error('403'))

		await expect(setSwimlaneOrder.call(store, { boardId: 4, type: 'labels', order: [2, 1] })).rejects.toThrow('403')
		expect(store.swimlaneLabelOrder[4]).toEqual([1, 2])
	})

	it('rolls the very first reorder back to an empty order', async () => {
		const store = makeStore()
		setConfig.mockRejectedValue(new Error('403'))

		await expect(setSwimlaneOrder.call(store, { boardId: 5, type: 'assignees', order: ['0:bob'] })).rejects.toThrow()
		expect(store.swimlaneUserOrder[5]).toEqual([])
	})

	it('does not let a failed older write undo a newer one', async () => {
		const store = makeStore()
		const first = deferred()
		const second = deferred()
		setConfig.mockReturnValueOnce(first.promise).mockReturnValueOnce(second.promise)

		const older = setSwimlaneOrder.call(store, { boardId: 6, type: 'labels', order: [2, 1] })
		const newer = setSwimlaneOrder.call(store, { boardId: 6, type: 'labels', order: [3, 2, 1] })
		first.reject(new Error('network'))
		second.resolve({ 'board:6:swimlaneLabelOrder': '[3,2,1]' })

		await expect(older).rejects.toThrow('network')
		await newer
		expect(store.swimlaneLabelOrder[6]).toEqual([3, 2, 1])
	})

	it('takes the order the server stored', async () => {
		const store = makeStore()
		// The server dropped the label that is gone.
		setConfig.mockResolvedValue({ 'board:4:swimlaneLabelOrder': '[3]' })

		await setSwimlaneOrder.call(store, { boardId: 4, type: 'labels', order: [3, 99] })

		expect(setConfig).toHaveBeenCalledWith({ 'board:4:swimlaneLabelOrder': '[3,99]' })
		expect(store.swimlaneLabelOrder[4]).toEqual([3])
	})

	it('takes the stored user order from its own key', async () => {
		const store = makeStore()
		setConfig.mockResolvedValue({ 'board:4:swimlaneUserOrder': '["0:bob"]' })

		await setSwimlaneOrder.call(store, { boardId: 4, type: 'assignees', order: ['bob', '0:bob'] })

		expect(store.swimlaneUserOrder[4]).toEqual(['0:bob'])
	})

	it('does not apply the answer of an older write over a newer one', async () => {
		const store = makeStore()
		const first = deferred()
		const second = deferred()
		setConfig.mockReturnValueOnce(first.promise).mockReturnValueOnce(second.promise)

		const older = setSwimlaneOrder.call(store, { boardId: 7, type: 'labels', order: [2, 1] })
		const newer = setSwimlaneOrder.call(store, { boardId: 7, type: 'labels', order: [3, 2, 1] })
		first.resolve({ 'board:7:swimlaneLabelOrder': '[2,1]' })
		await older
		expect(store.swimlaneLabelOrder[7]).toEqual([3, 2, 1])

		second.resolve({ 'board:7:swimlaneLabelOrder': '[3,1]' })
		await newer
		expect(store.swimlaneLabelOrder[7]).toEqual([3, 1])
	})

	it.each([
		['no answer', undefined],
		['an answer without the key', {}],
		['a value that is not a string', { 'board:4:swimlaneLabelOrder': 5 }],
		['a value that is not JSON', { 'board:4:swimlaneLabelOrder': '[3' }],
		['a value that is not a list', { 'board:4:swimlaneLabelOrder': '{"a":1}' }],
	])('keeps the order it sent for %s and does not throw', async (name, answer) => {
		const store = makeStore()
		store.swimlaneLabelOrder[4] = [1, 2]
		setConfig.mockResolvedValue(answer)

		await expect(setSwimlaneOrder.call(store, { boardId: 4, type: 'labels', order: [2, 1] })).resolves.toBeUndefined()
		expect(store.swimlaneLabelOrder[4]).toEqual([2, 1])
	})
})

describe('setSwimlaneMode', () => {
	it('rolls back when the server rejects the write', async () => {
		const store = makeStore()
		setConfig.mockRejectedValue(new Error('403'))

		await expect(setSwimlaneMode.call(store, { boardId: 4, mode: 'labels' })).rejects.toThrow('403')
		expect(store.currentBoard.settings.swimlaneMode).toBe('none')
	})

	it('does not let a failed older write undo a newer one', async () => {
		const store = makeStore()
		const first = deferred()
		setConfig.mockReturnValueOnce(first.promise).mockResolvedValueOnce()

		const older = setSwimlaneMode.call(store, { boardId: 4, mode: 'labels' })
		await setSwimlaneMode.call(store, { boardId: 4, mode: 'assignees' })
		first.reject(new Error('network'))

		await expect(older).rejects.toThrow('network')
		expect(store.currentBoard.settings.swimlaneMode).toBe('assignees')
	})

	it('leaves another board alone when a write fails after switching boards', async () => {
		const store = makeStore()
		const write = deferred()
		setConfig.mockReturnValueOnce(write.promise)

		const pending = setSwimlaneMode.call(store, { boardId: 4, mode: 'assignees' })
		store.currentBoard = { id: 5, settings: { swimlaneMode: 'labels' } }
		write.reject(new Error('network'))

		await expect(pending).rejects.toThrow('network')
		expect(store.currentBoard.settings.swimlaneMode).toBe('labels')
	})
})

describe('readSwimlaneSettings', () => {
	it('skips a malformed order and still reads the other one', () => {
		const store = makeStore()
		readSwimlaneSettings.call(store, 4, { settings: { swimlaneLabelOrder: '[2,1', swimlaneUserOrder: '["0:bob"]' } })

		expect(store.swimlaneLabelOrder[4]).toBeUndefined()
		expect(store.swimlaneUserOrder[4]).toEqual(['0:bob'])
	})

	it('skips an order that is valid JSON but not a list', () => {
		const store = makeStore()
		readSwimlaneSettings.call(store, 4, { settings: { swimlaneLabelOrder: '{"a":1}', swimlaneUserOrder: 'null' } })

		expect(store.swimlaneLabelOrder[4]).toBeUndefined()
		expect(store.swimlaneUserOrder[4]).toBeUndefined()
	})

	it('keeps the previous order when the new one is not a list', () => {
		const store = makeStore()
		readSwimlaneSettings.call(store, 4, { settings: { swimlaneLabelOrder: '[2,1]', swimlaneUserOrder: '["0:bob"]' } })
		readSwimlaneSettings.call(store, 4, { settings: { swimlaneLabelOrder: '{"a":1}', swimlaneUserOrder: '7' } })

		expect(store.swimlaneLabelOrder[4]).toEqual([2, 1])
		expect(store.swimlaneUserOrder[4]).toEqual(['0:bob'])
	})

	it('reads a missing order as empty', () => {
		const store = makeStore()
		readSwimlaneSettings.call(store, 4, { settings: { swimlaneMode: 'labels' } })

		expect(store.swimlaneLabelOrder[4]).toEqual([])
		expect(store.swimlaneUserOrder[4]).toEqual([])
	})
})

// The swimlane write state is module wide, so each case uses its own board id and settles all its writes.
describe('refreshBoard', () => {
	it('keeps an order whose write finished while the load ran', async () => {
		const store = makeRefreshStore(10)
		store.swimlaneLabelOrder[10] = [1, 2]
		const post = deferred()
		const get = deferred()
		setConfig.mockReturnValueOnce(post.promise)
		mockLoadById.mockReturnValueOnce(get.promise)

		const write = setSwimlaneOrder.call(store, { boardId: 10, type: 'labels', order: [2, 1] })
		const refresh = refreshBoard.call(store, 10)
		post.resolve({ 'board:10:swimlaneLabelOrder': '[2,1]' })
		await write
		// The load started before the write finished, so its answer may predate it.
		get.resolve(serverBoard(10, { swimlaneLabelOrder: '[1,2]' }))
		await refresh

		expect(store.swimlaneLabelOrder[10]).toEqual([2, 1])
	})

	it('keeps an order whose write started after the load and is still open', async () => {
		const store = makeRefreshStore(11)
		store.swimlaneLabelOrder[11] = [1, 2]
		const post = deferred()
		const get = deferred()
		setConfig.mockReturnValueOnce(post.promise)
		mockLoadById.mockReturnValueOnce(get.promise)

		const refresh = refreshBoard.call(store, 11)
		const write = setSwimlaneOrder.call(store, { boardId: 11, type: 'labels', order: [2, 1] })
		get.resolve(serverBoard(11, { swimlaneLabelOrder: '[1,2]' }))
		await refresh
		expect(store.swimlaneLabelOrder[11]).toEqual([2, 1])

		post.resolve({ 'board:11:swimlaneLabelOrder': '[2,1]' })
		await write
		expect(store.swimlaneLabelOrder[11]).toEqual([2, 1])
	})

	it('keeps an order whose write started and finished after the load started', async () => {
		const store = makeRefreshStore(12)
		store.swimlaneLabelOrder[12] = [1, 2]
		const get = deferred()
		setConfig.mockResolvedValueOnce({ 'board:12:swimlaneLabelOrder': '[2,1]' })
		mockLoadById.mockReturnValueOnce(get.promise)

		const refresh = refreshBoard.call(store, 12)
		await setSwimlaneOrder.call(store, { boardId: 12, type: 'labels', order: [2, 1] })
		get.resolve(serverBoard(12, { swimlaneLabelOrder: '[1,2]' }))
		await refresh

		expect(store.swimlaneLabelOrder[12]).toEqual([2, 1])
	})

	it('keeps a mode whose write is still open', async () => {
		const store = makeRefreshStore(13)
		const post = deferred()
		setConfig.mockReturnValueOnce(post.promise)
		mockLoadById.mockResolvedValueOnce(serverBoard(13, { swimlaneMode: 'none' }))

		const write = setSwimlaneMode.call(store, { boardId: 13, mode: 'labels' })
		await refreshBoard.call(store, 13)
		expect(store.currentBoard.settings.swimlaneMode).toBe('labels')

		post.resolve()
		await write
	})

	it('keeps a mode whose write finished while the load ran', async () => {
		const store = makeRefreshStore(16)
		const post = deferred()
		const get = deferred()
		setConfig.mockReturnValueOnce(post.promise)
		mockLoadById.mockReturnValueOnce(get.promise)

		const write = setSwimlaneMode.call(store, { boardId: 16, mode: 'labels' })
		const refresh = refreshBoard.call(store, 16)
		post.resolve()
		await write
		get.resolve(serverBoard(16, { swimlaneMode: 'none' }))
		await refresh

		expect(store.currentBoard.settings.swimlaneMode).toBe('labels')
	})

	it('rolls a failed mode write back after a refresh and reads the next one', async () => {
		const store = makeRefreshStore(15)
		const post = deferred()
		setConfig.mockReturnValueOnce(post.promise)
		mockLoadById.mockResolvedValueOnce(serverBoard(15, { swimlaneMode: 'none' }))

		const write = setSwimlaneMode.call(store, { boardId: 15, mode: 'labels' })
		await refreshBoard.call(store, 15)
		// The refresh kept the optimistic mode, so the rollback below has something to undo.
		expect(store.currentBoard.settings.swimlaneMode).toBe('labels')
		post.reject(new Error('network'))
		await expect(write).rejects.toThrow('network')
		expect(store.currentBoard.settings.swimlaneMode).toBe('none')

		// The failed write no longer counts as open, so a later refresh is not skipped.
		mockLoadById.mockResolvedValueOnce(serverBoard(15, { swimlaneMode: 'assignees' }))
		await refreshBoard.call(store, 15)
		expect(store.currentBoard.settings.swimlaneMode).toBe('assignees')
	})

	it('takes the order and mode another member saved once no own write is involved', async () => {
		const store = makeRefreshStore(14)
		store.swimlaneLabelOrder[14] = [1, 2]
		setConfig.mockResolvedValue({ 'board:14:swimlaneLabelOrder': '[1,2]' })
		await setSwimlaneOrder.call(store, { boardId: 14, type: 'labels', order: [1, 2] })
		await setSwimlaneMode.call(store, { boardId: 14, mode: 'labels' })
		mockLoadById.mockResolvedValueOnce(serverBoard(14, { swimlaneLabelOrder: '[9,1]', swimlaneMode: 'assignees' }))

		await refreshBoard.call(store, 14)

		expect(store.swimlaneLabelOrder[14]).toEqual([9, 1])
		expect(store.currentBoard.settings.swimlaneMode).toBe('assignees')
	})
})
