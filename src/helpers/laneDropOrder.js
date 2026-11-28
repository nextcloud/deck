/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import applyOrderToArray from './applyOrderToArray.js'

/**
 * Translate a drop index inside a lane into a position in the stack's full
 * card list. The card goes in front of the lane card after it, or behind the
 * lane card before it when dropped at the end of the lane.
 *
 * @param {Array} laneCards cards the lane renders, as they were before the drop
 * @param {Array} stackCards all cards of the stack, as they were before the drop
 * @param {object} card the dropped card
 * @param {number|null} removedIndex lane-local index the card was dragged from,
 *   null when it came from another stack
 * @param {number} addedIndex lane-local index the card was dropped at
 * @return {number} position in the stack's full card list
 */
export default function laneDropOrder(laneCards, stackCards, card, removedIndex, addedIndex) {
	const laneAfterDrop = applyOrderToArray(laneCards, removedIndex, addedIndex, card)

	const remaining = []
	for (const stackCard of stackCards) {
		if (stackCard.id !== card.id) {
			remaining.push(stackCard)
		}
	}

	const positionOf = function(neighbour) {
		if (!neighbour) {
			return -1
		}
		for (let i = 0; i < remaining.length; i++) {
			if (remaining[i].id === neighbour.id) {
				return i
			}
		}
		return -1
	}

	const after = positionOf(laneAfterDrop[addedIndex + 1])
	if (after !== -1) {
		return after
	}

	const before = positionOf(laneAfterDrop[addedIndex - 1])
	if (before !== -1) {
		return before + 1
	}

	// No neighbour in the lane, append.
	return remaining.length
}
