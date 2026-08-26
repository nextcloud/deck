/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { beforeEach, describe, expect, it, jest } from '@jest/globals'
import Stack from './Stack.vue'

jest.mock('@nextcloud/dialogs', () => ({ showUndo: jest.fn() }), { virtual: true })
jest.mock('@nextcloud/dialogs/style.css', () => ({}), { virtual: true })
jest.mock('@nextcloud/vue', () => ({}), { virtual: true })
jest.mock('@vueuse/components', () => ({}), { virtual: true })
jest.mock('pinia', () => ({ mapActions: () => ({}), mapState: () => ({}) }))
jest.mock('vue3-smooth-dnd', () => ({}))
jest.mock('vue-material-design-icons/ArchiveOutline.vue', () => ({}))
jest.mock('vue-material-design-icons/CheckCircleOutline.vue', () => ({}))
jest.mock('../cards/CardItem.vue', () => ({}))
jest.mock('./StackCardAdd.vue', () => ({}))
jest.mock('../../logger.js', () => ({ __esModule: true, default: { debug: jest.fn() } }))
jest.mock('../../stores/board.js', () => ({}))
jest.mock('../../stores/card.js', () => ({}))
jest.mock('../../stores/settings.js', () => ({}))
jest.mock('../../stores/stack.js', () => ({}))
jest.mock('../../stores/trashbin.js', () => ({}))

function context() {
	return {
		stack: { id: 1 },
		orderForDrop: jest.fn().mockReturnValue(2),
		reorderCardInStore: jest.fn().mockResolvedValue(),
	}
}

beforeEach(() => {
	jest.clearAllMocks()
})

describe('drop order inside a lane', () => {
	const A = { id: 1, archived: false }
	const B = { id: 2, archived: true }
	const C = { id: 3, archived: false }
	const D = { id: 4, archived: false }

	function laneContext(showArchived = false) {
		return {
			lane: { key: 'label-1' },
			stack: { id: 1 },
			showArchived,
			cardsByStack: [A, C],
			cardsByStackGetter: () => [A, B, C, D],
		}
	}

	it('translates the index against the stack without archived cards', () => {
		// The server counts [A, C, D] without the archived B, so A behind C is
		// position 1. Counting B would give 2.
		expect(Stack.methods.orderForDrop.call(laneContext(), A, 0, 1)).toBe(1)
	})

	it('passes the index through without a lane', () => {
		const stack = { lane: null, cardsByStack: [A, C], cardsByStackGetter: () => [A, B, C, D] }
		expect(Stack.methods.orderForDrop.call(stack, C, 1, 0)).toBe(0)
	})
})

describe('card drop inside a stack', () => {
	it('ignores a drop back onto the picked-up position', async () => {
		const stack = context()
		await Stack.methods.onDropCard.call(stack, 1, { addedIndex: 1, removedIndex: 1, payload: { id: 5 } })
		expect(stack.orderForDrop).not.toHaveBeenCalled()
		expect(stack.reorderCardInStore).not.toHaveBeenCalled()
	})

	it('reorders the card when it lands on another position', async () => {
		const stack = context()
		await Stack.methods.onDropCard.call(stack, 1, { addedIndex: 1, removedIndex: 0, payload: { id: 5 } })
		expect(stack.reorderCardInStore).toHaveBeenCalledTimes(1)
		expect(stack.reorderCardInStore).toHaveBeenCalledWith(expect.objectContaining({ id: 5, order: 2 }))
	})

	it('still moves a card dropped in from another stack', async () => {
		const stack = context()
		await Stack.methods.onDropCard.call(stack, 1, { addedIndex: 0, removedIndex: null, payload: { id: 5, stackId: 9 } })
		expect(stack.reorderCardInStore).toHaveBeenCalledWith(expect.objectContaining({ id: 5, stackId: 1, order: 2 }))
	})
})
