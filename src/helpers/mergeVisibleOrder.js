/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

/**
 * Write the reordered visible lanes back into the stored order. Visible ids
 * take the slots they already had, hidden lanes (filtered or empty) keep
 * theirs. Unknown ids stay, the lane sort ignores them.
 *
 * @param {Array|undefined} stored the order as it is saved for the board
 * @param {Array} visible the reordered ids of the lanes on screen
 * @return {Array} the order to save
 */
export default function mergeVisibleOrder(stored, visible) {
	if (!stored || stored.length === 0) {
		return visible
	}
	const visibleIds = new Set(visible)
	const order = []
	let next = 0
	for (const id of stored) {
		if (visibleIds.has(id) && next < visible.length) {
			order.push(visible[next])
			next += 1
		} else {
			order.push(id)
		}
	}
	while (next < visible.length) {
		order.push(visible[next])
		next += 1
	}
	return order
}
