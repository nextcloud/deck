/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { beforeEach, expect, jest, test } from '@jest/globals'
import { createPinia } from 'pinia'
import { BoardApi } from '../services/BoardApi.js'
import { CardApi } from '../services/CardApi.js'
import { StackApi } from '../services/StackApi.js'
import { useBoardStore } from '../stores/board.js'
import { useCardStore } from '../stores/card.js'
import { useStackStore } from '../stores/stack.js'
import { useTrashbinStore } from '../stores/trashbin.js'

jest.mock('@vue/devtools-api', () => ({ setupDevtoolsPlugin: jest.fn() }))
jest.mock('@nextcloud/initial-state', () => ({ loadState: (_, key) => key === 'initialBoards' ? [] : {} }), { virtual: true })
jest.mock('@nextcloud/axios', () => ({}), { virtual: true })
jest.mock('@nextcloud/router', () => ({ generateUrl: jest.fn() }), { virtual: true })
jest.mock('../logger.js', () => ({ error: jest.fn() }))
jest.mock('moment', () => jest.fn())
jest.mock('../services/BoardApi.js', () => ({ BoardApi: jest.fn() }))
jest.mock('../services/StackApi.js', () => ({ StackApi: jest.fn() }))
jest.mock('../services/CardApi.js', () => ({ CardApi: jest.fn() }))

const labelApi = jest.fn()
const deleteApi = jest.fn()
const restoreApi = jest.fn()
const restoreCardApi = jest.fn()
const loadArchivedApi = jest.fn()

beforeEach(() => {
	BoardApi.mock.instances[0].createLabel = labelApi
	for (const instance of StackApi.mock.instances) {
		instance.deleteStack = deleteApi
		instance.updateStack = restoreApi
		instance.loadArchivedStacks = loadArchivedApi
	}
	for (const instance of CardApi.mock.instances) {
		instance.updateCard = restoreCardApi
	}
})

function deferred() {
	let resolve
	const promise = new Promise((done) => {
		resolve = done
	})
	return { promise, resolve }
}

test('creating a label keeps the original card store after another instance activates', async () => {
	const first = createPinia()
	const second = createPinia()
	const board = useBoardStore(first)
	board.currentBoard = { id: 42, labels: [] }
	const cards = useCardStore(first)
	cards.addLabel = jest.fn().mockResolvedValue()
	const pending = deferred()
	labelApi.mockReturnValue(pending.promise)
	const operation = board.addLabelToCurrentBoardAndCard({ newLabel: {}, card: { labels: [] } })
	const otherCards = useCardStore(second)
	otherCards.addLabel = jest.fn()
	pending.resolve({ id: 7 })
	await operation
	expect(cards.addLabel).toHaveBeenCalledTimes(1)
	expect(otherCards.addLabel).not.toHaveBeenCalled()
})

test('deleting a list keeps the original trash store after another instance activates', async () => {
	const first = createPinia()
	const stacks = useStackStore(first)
	const trash = useTrashbinStore(first)
	trash.moveStackToTrash = jest.fn()
	const pending = deferred()
	deleteApi.mockReturnValue(pending.promise)
	stacks.deleteStack({ id: 1, boardId: 42 })
	const otherTrash = useTrashbinStore(createPinia())
	otherTrash.moveStackToTrash = jest.fn()
	pending.resolve({ id: 1, boardId: 42 })
	await pending.promise
	expect(trash.moveStackToTrash).toHaveBeenCalledTimes(1)
	expect(otherTrash.moveStackToTrash).not.toHaveBeenCalled()
})

test('restoring a list keeps the original stack store after another instance activates', async () => {
	const first = createPinia()
	const trash = useTrashbinStore(first)
	const stacks = useStackStore(first)
	stacks.addStack = jest.fn()
	const pending = deferred()
	restoreApi.mockReturnValue(pending.promise)
	trash.stackUndoDelete({ id: 1, boardId: 42 })
	const otherStacks = useStackStore(createPinia())
	otherStacks.addStack = jest.fn()
	pending.resolve({ id: 1, boardId: 42 })
	await pending.promise
	expect(stacks.addStack).toHaveBeenCalledTimes(1)
	expect(otherStacks.addStack).not.toHaveBeenCalled()
})

test('restoring a card keeps the original card store after another instance activates', async () => {
	const first = createPinia()
	const trash = useTrashbinStore(first)
	const cards = useCardStore(first)
	const card = { id: 7, boardId: 42, deletedAt: 1 }
	trash.setDeletedCards([card])
	const pending = deferred()
	restoreCardApi.mockReturnValue(pending.promise)
	trash.cardUndoDelete(card)
	const otherCards = useCardStore(createPinia())
	pending.resolve({ ...card, deletedAt: 0 })
	await pending.promise
	expect(cards.cardById(7)).toMatchObject({ deletedAt: 0 })
	expect(trash.deletedCards).toEqual([])
	expect(otherCards.cards).toEqual([])
})

test('legacy archived stack loading still replaces the current card list', async () => {
	const pinia = createPinia()
	const cards = useCardStore(pinia)
	cards.setCards([{ id: 7, stackId: 1, archived: false }])
	loadArchivedApi.mockResolvedValue([{
		id: 1,
		boardId: 42,
		cards: [{ id: 8, stackId: 1, archived: true }],
	}])
	await useStackStore(pinia).loadArchivedStacks(42)
	expect(cards.cards.map((card) => card.id)).toEqual([8])
})
