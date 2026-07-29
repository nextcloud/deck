/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

/**
 * Sort labels by their manual order (see LabelService::reorder), unordered
 * labels last (alphabetically), matching the backend sorting.
 *
 * @param {Array} labels array of label objects ({ id, title, order? })
 * @return {Array} new sorted array
 */
export function sortLabels(labels) {
	return [...labels].sort((a, b) => {
		const orderA = a.order ?? null
		const orderB = b.order ?? null
		if (orderA !== null && orderB !== null && orderA !== orderB) {
			return orderA - orderB
		}
		if ((orderA === null) !== (orderB === null)) {
			return orderA === null ? 1 : -1
		}
		return (a.title ?? '').localeCompare(b.title ?? '')
	})
}
