/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { beforeEach, describe, expect, it, jest } from '@jest/globals'
import { useBoardStore } from './board.js'
import { useCardStore } from './card.js'

// defineStore hands back the options, so a getter runs without a Pinia instance.
jest.mock('pinia', () => ({ defineStore: (id, options) => options }))
jest.mock('moment', () => ({}), { virtual: true })
jest.mock('../services/CardApi.js', () => ({ CardApi: jest.fn() }))
jest.mock('./board.js', () => ({ useBoardStore: jest.fn() }))
jest.mock('./settings.js', () => ({ useSettingsStore: jest.fn() }))
jest.mock('./trashbin.js', () => ({ useTrashbinStore: jest.fn() }))
jest.mock('./stack.js', () => ({
	useStackStore: () => ({
		// Stack 10 belongs to the open board 4, stack 20 to board 6.
		stackById: (id) => ({ 10: { id: 10, boardId: 4 }, 20: { id: 20, boardId: 6 } })[id],
	}),
}))

const twoLabels = [{ id: 1 }, { id: 2 }]

function laneCount(card, swimlaneMode = 'labels') {
	useBoardStore.mockReturnValue({ swimlaneMode, currentBoard: { id: 4 } })
	return useCardStore.getters.cardLaneCount()(card)
}

beforeEach(() => {
	jest.clearAllMocks()
})

describe('cardLaneCount', () => {
	it('counts the lanes of a card on the open board', () => {
		expect(laneCount({ stackId: 10, labels: twoLabels })).toBe(2)
	})

	it('returns 0 for a card that sits in one lane', () => {
		expect(laneCount({ stackId: 10, labels: [{ id: 1 }] })).toBe(0)
	})

	it('returns 0 when the board is not grouped', () => {
		expect(laneCount({ stackId: 10, labels: twoLabels }, 'none')).toBe(0)
	})

	// A search hit from another board has no lanes on this one, whatever its
	// labels are.
	it('returns 0 for a card of another board', () => {
		expect(laneCount({ stackId: 20, labels: twoLabels })).toBe(0)
	})

	it('returns 0 for a card whose stack is not loaded', () => {
		expect(laneCount({ stackId: 99, labels: twoLabels })).toBe(0)
	})
})
