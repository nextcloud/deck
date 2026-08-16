/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
import { sortBoards } from './boardSort.js'

describe('sortBoards', () => {
	it('sorts alphabetically without a custom order', () => {
		const boards = [{ id: 1, title: 'Zebra' }, { id: 2, title: 'Alpha' }]
		expect(sortBoards(boards, null).map(b => b.id)).toEqual([2, 1])
	})

	it('applies the custom order first', () => {
		const boards = [{ id: 1, title: 'Alpha' }, { id: 2, title: 'Beta' }, { id: 3, title: 'Gamma' }]
		expect(sortBoards(boards, [3, 1]).map(b => b.id)).toEqual([3, 1, 2])
	})

	it('appends unknown boards alphabetically after ordered ones', () => {
		const boards = [{ id: 5, title: 'Zzz' }, { id: 6, title: 'Aaa' }, { id: 7, title: 'Mmm' }]
		expect(sortBoards(boards, [7]).map(b => b.id)).toEqual([7, 6, 5])
	})

	it('ignores ids of removed boards', () => {
		const boards = [{ id: 1, title: 'B' }, { id: 2, title: 'A' }]
		expect(sortBoards(boards, [999, 2]).map(b => b.id)).toEqual([2, 1])
	})
})
