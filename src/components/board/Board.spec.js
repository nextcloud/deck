/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { beforeEach, expect, jest, test } from '@jest/globals'
import { subscribe, unsubscribe } from '@nextcloud/event-bus'
import Board from './Board.vue'
import { createSession } from '../../sessions.js'

jest.mock('@nextcloud/event-bus', () => ({ subscribe: jest.fn(), unsubscribe: jest.fn() }), { virtual: true })
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
jest.mock('../../logger.js', () => ({ error: jest.fn() }))
jest.mock('../../helpers/errors.js', () => ({ showError: jest.fn() }))
jest.mock('../../sessions.js', () => ({ createSession: jest.fn() }))
jest.mock('../../stores/board.js', () => ({ useBoardStore: jest.fn() }))
jest.mock('../../stores/card.js', () => ({ useCardStore: jest.fn() }))
jest.mock('../../stores/settings.js', () => ({ useSettingsStore: jest.fn() }))
jest.mock('../../stores/stack.js', () => ({ useStackStore: jest.fn() }))

beforeEach(() => jest.clearAllMocks())

function viewModel() {
	return {
		id: 42,
		board: { id: 42 },
		disposed: false,
		deckEmbedding: null,
		loadBoardById: jest.fn().mockResolvedValue(),
		loadStacks: jest.fn().mockResolvedValue(),
		$emit: jest.fn(),
		$route: { params: {} },
		stopMouseDrag: jest.fn(),
	}
}

test('normal board still loads through the legacy session path', async () => {
	const vm = viewModel()
	await Board.methods.fetchData.call(vm)
	expect(vm.loadBoardById).toHaveBeenCalledWith(42)
	expect(vm.loadStacks).toHaveBeenCalledWith(42)
	expect(createSession).toHaveBeenCalledWith(42)
	expect(vm.$emit).toHaveBeenCalledWith('ready', vm.board)
	expect(vm.loading).toBe(false)
})

test('embedded board uses its own session provider', async () => {
	const vm = viewModel()
	vm.deckEmbedding = { createSession: jest.fn() }
	await Board.methods.fetchData.call(vm)
	expect(vm.deckEmbedding.createSession).toHaveBeenCalledWith(42)
	expect(createSession).not.toHaveBeenCalled()
})

test('load failures keep the existing error path and emit error', async () => {
	const vm = viewModel()
	const error = new Error('Denied')
	vm.loadBoardById.mockRejectedValue(error)
	await Board.methods.fetchData.call(vm)
	expect(vm.$emit).toHaveBeenCalledWith('error', error)
	expect(vm.$emit).not.toHaveBeenCalledWith('ready', vm.board)
	expect(vm.loading).toBe(false)
	expect(createSession).not.toHaveBeenCalled()
})

test('unmounted board cannot create a late session', async () => {
	const vm = viewModel()
	vm.loadBoardById.mockImplementation(async () => {
		vm.disposed = true
	})
	await Board.methods.fetchData.call(vm)
	expect(vm.loadStacks).not.toHaveBeenCalled()
	expect(createSession).not.toHaveBeenCalled()
})

test('legacy boards retain their event-bus subscription', () => {
	const vm = viewModel()
	vm.fetchData = jest.fn()
	vm.openCardModal = jest.fn()
	Board.created.call(vm)
	expect(subscribe).toHaveBeenCalledWith('deck:card:open-modal', vm.openCardModal)
	Board.beforeUnmount.call(vm)
	expect(unsubscribe).toHaveBeenCalledWith('deck:card:open-modal', vm.openCardModal)
})

test('embedded boards do not listen to global card-opening events', () => {
	const vm = viewModel()
	vm.deckEmbedding = {}
	vm.fetchData = jest.fn()
	Board.created.call(vm)
	Board.beforeUnmount.call(vm)
	expect(subscribe).not.toHaveBeenCalled()
	expect(unsubscribe).not.toHaveBeenCalled()
})
