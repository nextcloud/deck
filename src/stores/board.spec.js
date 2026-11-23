/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { beforeEach, describe, expect, it, jest } from '@jest/globals'
import { useBoardStore } from './board.js'
import { useSettingsStore } from './settings.js'

// defineStore hands back the options, so an action runs on a plain object.
jest.mock('pinia', () => ({ defineStore: (id, options) => options }))
jest.mock('@nextcloud/axios', () => ({}), { virtual: true })
jest.mock('@nextcloud/initial-state', () => ({ loadState: jest.fn() }), { virtual: true })
jest.mock('@nextcloud/router', () => ({ generateUrl: jest.fn() }), { virtual: true })
jest.mock('../services/BoardApi.js', () => ({ BoardApi: jest.fn() }))
jest.mock('./card.js', () => ({ useCardStore: jest.fn() }))
jest.mock('./settings.js', () => ({ useSettingsStore: jest.fn() }))
jest.mock('./stack.js', () => ({ useStackStore: jest.fn() }))

const { setSwimlaneMode, setSwimlaneOrder, readSwimlaneOrder } = useBoardStore.actions

function makeStore() {
	return {
		currentBoard: { id: 4, settings: { swimlaneMode: 'none' } },
		swimlaneLabelOrder: {},
		swimlaneUserOrder: {},
	}
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

describe('readSwimlaneOrder', () => {
	it('skips a malformed order and still reads the other one', () => {
		const store = makeStore()
		readSwimlaneOrder.call(store, 4, { settings: { swimlaneLabelOrder: '[2,1', swimlaneUserOrder: '["0:bob"]' } })

		expect(store.swimlaneLabelOrder[4]).toBeUndefined()
		expect(store.swimlaneUserOrder[4]).toEqual(['0:bob'])
	})

	it('reads a missing order as empty', () => {
		const store = makeStore()
		readSwimlaneOrder.call(store, 4, { settings: { swimlaneMode: 'labels' } })

		expect(store.swimlaneLabelOrder[4]).toEqual([])
		expect(store.swimlaneUserOrder[4]).toEqual([])
	})
})
