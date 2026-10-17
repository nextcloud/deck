/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { beforeEach, expect, jest, test } from '@jest/globals'
import { showUndo } from '@nextcloud/dialogs'
import { createPinia, defineStore, setActivePinia } from 'pinia'
import NcActionsCardMenuEntries from './NcActionsCardMenuEntries.vue'
import { useTrashbinStore } from '../../stores/trashbin.js'

jest.mock('@vue/devtools-api', () => ({ setupDevtoolsPlugin: jest.fn() }))
jest.mock('@nextcloud/auth', () => ({}), { virtual: true })
jest.mock('@nextcloud/dialogs', () => ({ showUndo: jest.fn() }), { virtual: true })
jest.mock('@nextcloud/dialogs/style.css', () => ({}), { virtual: true })
jest.mock('@nextcloud/event-bus', () => ({}), { virtual: true })
jest.mock('@nextcloud/router', () => ({}), { virtual: true })
jest.mock('@nextcloud/vue', () => ({}), { virtual: true })
jest.mock('vue-material-design-icons/AccountMinusOutline.vue', () => ({}))
jest.mock('vue-material-design-icons/AccountPlusOutline.vue', () => ({}))
jest.mock('vue-material-design-icons/ArchiveOutline.vue', () => ({}))
jest.mock('vue-material-design-icons/CardBulletedOutline.vue', () => ({}))
jest.mock('vue-material-design-icons/Check.vue', () => ({}))
jest.mock('vue-material-design-icons/Circle.vue', () => ({}))
jest.mock('vue-material-design-icons/Delete.vue', () => ({}))
jest.mock('vue-material-design-icons/OpenInNew.vue', () => ({}))
jest.mock('vue-material-design-icons/PencilOutline.vue', () => ({}))
jest.mock('../../stores/actions.js', () => ({}))
jest.mock('../../stores/board.js', () => ({}))
jest.mock('../../stores/card.js', () => ({}))
jest.mock('../../stores/stack.js', () => ({}))
jest.mock('../../stores/trashbin.js', () => ({ useTrashbinStore: jest.fn() }))

beforeEach(() => jest.clearAllMocks())

test('undo uses the deleting component store even when another board is active', () => {
	const trashStore = defineStore('undo-test', {
		actions: { cardUndoDelete: jest.fn() },
	})
	useTrashbinStore.mockImplementation(trashStore)
	const first = createPinia()
	const second = createPinia()
	const firstTrash = trashStore(first)
	const secondTrash = trashStore(second)
	firstTrash.cardUndoDelete = jest.fn()
	secondTrash.cardUndoDelete = jest.fn()
	setActivePinia(second)
	globalThis.t = (_, text) => text
	try {
		const card = { id: 7, stackId: 1 }
		const vm = {
			$pinia: first,
			card,
			boardId: 42,
			deleteCardInStore: jest.fn(),
			$router: { currentRoute: { name: 'board' } },
		}
		NcActionsCardMenuEntries.methods.deleteCard.call(vm)
		expect(vm.deleteCardInStore).toHaveBeenCalledWith(card)
		expect(useTrashbinStore).toHaveBeenCalledWith(first)
		setActivePinia(second)
		showUndo.mock.calls[0][1]()
		expect(firstTrash.cardUndoDelete).toHaveBeenCalledWith({ ...card, deletedAt: 0, boardId: 42 })
		expect(secondTrash.cardUndoDelete).not.toHaveBeenCalled()
	} finally {
		delete globalThis.t
	}
})
