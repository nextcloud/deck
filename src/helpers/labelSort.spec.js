/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
import { sortLabels } from './labelSort.js'

describe('sortLabels', () => {
	it('sorts by manual order when present', () => {
		const labels = [
			{ id: 1, title: 'B', order: 2 },
			{ id: 2, title: 'A', order: 0 },
			{ id: 3, title: 'C', order: 1 },
		]
		expect(sortLabels(labels).map(l => l.id)).toEqual([2, 3, 1])
	})

	it('falls back to alphabetical when no order is set', () => {
		const labels = [
			{ id: 1, title: 'Zebra' },
			{ id: 2, title: 'alpha', order: null },
		]
		expect(sortLabels(labels).map(l => l.id)).toEqual([2, 1])
	})

	it('puts ordered labels before unordered ones', () => {
		const labels = [
			{ id: 1, title: 'AAA', order: null },
			{ id: 2, title: 'ZZZ', order: 0 },
		]
		expect(sortLabels(labels).map(l => l.id)).toEqual([2, 1])
	})

	it('does not mutate the input array', () => {
		const labels = [{ id: 1, title: 'b' }, { id: 2, title: 'a' }]
		sortLabels(labels)
		expect(labels.map(l => l.id)).toEqual([1, 2])
	})
})
