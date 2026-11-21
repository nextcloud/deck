/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { describe, expect, it } from '@jest/globals'
import laneDropOrder from './laneDropOrder.js'

const A = { id: 1 }
const B = { id: 2 }
const C = { id: 3 }
const D = { id: 4 }
const X = { id: 9 }

describe('laneDropOrder', () => {
	// Stack [A, B, C], lane shows [A, C] because B carries a different label.
	// Dragging A below C reports addedIndex 1, which as a stack position would
	// mean "in front of C" and leave the lane looking untouched.
	it('moves a card behind its new lane neighbour, not to the lane index', () => {
		expect(laneDropOrder([A, C], [A, B, C], A, 0, 1)).toBe(2)
	})

	it('moves a card in front of its new lane neighbour', () => {
		expect(laneDropOrder([A, C], [A, B, C], C, 1, 0)).toBe(0)
	})

	it('places a card dropped from another stack in front of the following lane card', () => {
		expect(laneDropOrder([A, C], [A, B, C], X, null, 1)).toBe(2)
	})

	it('appends a card dropped into an empty lane', () => {
		expect(laneDropOrder([], [A, B, C], X, null, 0)).toBe(3)
	})

	// The only card in the lane cannot anchor on itself.
	it('appends when the lane holds nothing but the dropped card', () => {
		expect(laneDropOrder([A], [A, B, C], A, 0, 0)).toBe(2)
	})

	// Lane [A, C] of stack [A, B, C, D]. The end of the lane is behind C, not
	// the end of the stack, which D of another lane still occupies.
	it('drops at the end of a lane behind its last card, not at the stack end', () => {
		expect(laneDropOrder([A, C], [A, B, C, D], X, null, 2)).toBe(3)
	})
})
