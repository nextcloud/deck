/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

/**
 * Write the reordered visible lanes back into the stored order. Visible ids
 * take the slots they already had, hidden lanes (filtered or empty) keep
 * theirs. Unknown ids stay, the lane sort ignores them. Repeated entries in
 * the stored order are dropped, the server refuses duplicates.
 *
 * @param {Array<number|string>|undefined} stored the order as it is saved for the board
 * @param {Array<number|string>} visible the reordered lane keys on screen
 * @return {Array<number|string>} the order to save
 */
export default function mergeVisibleOrder(stored, visible) {
	if (!stored || stored.length === 0) {
		return visible
	}
	const visibleIds = new Set(visible)
	const order = []
	const seen = new Set()
	let next = 0
	for (const id of stored) {
		if (seen.has(id)) {
			continue
		}
		seen.add(id)
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
