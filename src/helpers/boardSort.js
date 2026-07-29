/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

/**
 * Sort boards by the user defined order (list of board ids), boards not in
 * the list come last, alphabetically.
 *
 * @param {Array} boards array of board objects ({ id, title })
 * @param {Array|null} order ordered list of board ids or null
 * @return {Array} new sorted array
 */
export function sortBoards(boards, order) {
	const position = new Map((order ?? []).map((id, index) => [id, index]))
	return [...boards].sort((a, b) => {
		const positionA = position.has(a.id) ? position.get(a.id) : Number.POSITIVE_INFINITY
		const positionB = position.has(b.id) ? position.get(b.id) : Number.POSITIVE_INFINITY
		if (positionA !== positionB) {
			return positionA - positionB
		}
		return a.title.localeCompare(b.title)
	})
}
