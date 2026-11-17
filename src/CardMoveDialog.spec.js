/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { describe, expect, it, jest } from '@jest/globals'
import CardMoveDialog from './CardMoveDialog.vue'

jest.mock('@nextcloud/axios', () => ({}), { virtual: true })
jest.mock('@nextcloud/event-bus', () => ({}))
jest.mock('@nextcloud/router', () => ({}), { virtual: true })
jest.mock('@nextcloud/vue', () => ({}), { virtual: true })
jest.mock('pinia', () => ({
	mapActions: () => ({}),
	mapState: () => ({}),
}))
jest.mock('./stores/board.js', () => ({}))
jest.mock('./stores/card.js', () => ({}))
jest.mock('./stores/stack.js', () => ({}))

const { activeBoards, canMoveCard } = CardMoveDialog.computed

function context(permissions, card = { boardId: 1, stackId: 2 }) {
	return {
		card,
		boardById: (id) => (id === 1 ? { permissions } : undefined),
		stackById: (id) => (id === 2 ? { boardId: 1 } : undefined),
	}
}

describe('CardMoveDialog', () => {
	it('allows moving a card off a board the user can edit', () => {
		expect(canMoveCard.call(context({ PERMISSION_EDIT: true }))).toBe(true)
	})

	it('does not allow moving a card off a read-only board', () => {
		expect(canMoveCard.call(context({ PERMISSION_EDIT: false }))).toBe(false)
	})

	it('falls back to the stack when the card has no board id', () => {
		expect(canMoveCard.call(context({ PERMISSION_EDIT: true }, { stackId: 2 }))).toBe(true)
	})

	it('only offers boards the user can edit as a target', () => {
		const board = (id, canEdit) => ({ id, deletedAt: 0, archived: false, permissions: { PERMISSION_EDIT: canEdit } })
		const boards = [board(1, true), board(2, false)]

		expect(activeBoards.call({ boards }).map(({ id }) => id)).toEqual([1])
	})
})
