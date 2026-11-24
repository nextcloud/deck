/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { describe, expect, it } from '@jest/globals'
import mergeVisibleOrder from './mergeVisibleOrder.js'

describe('mergeVisibleOrder', () => {
	it('takes the dragged sequence when nothing is stored yet', () => {
		expect(mergeVisibleOrder(undefined, ['a', 'b'])).toEqual(['a', 'b'])
		expect(mergeVisibleOrder([], ['a', 'b'])).toEqual(['a', 'b'])
	})

	// The whole point of the helper: B is filtered out of the view, so the
	// dragged sequence never mentions it. Writing that sequence would delete
	// B's position for every other board member.
	it('leaves a hidden lane in its slot', () => {
		expect(mergeVisibleOrder(['a', 'b', 'c'], ['c', 'a'])).toEqual(['c', 'b', 'a'])
	})

	it('passes an unchanged sequence through untouched', () => {
		expect(mergeVisibleOrder(['a', 'b', 'c'], ['a', 'b', 'c'])).toEqual(['a', 'b', 'c'])
	})

	it('appends a lane that has no stored position yet', () => {
		expect(mergeVisibleOrder(['a', 'b'], ['a', 'b', 'c'])).toEqual(['a', 'b', 'c'])
	})

	// Pinned deliberately, because it is a property of filling slots in
	// sequence rather than an obviously correct outcome: 'b' was not dragged,
	// yet it ends up behind the hidden 'c'. Changing this means changing the
	// merge strategy, and that should not happen by accident.
	it('can push an untouched lane past a hidden one', () => {
		expect(mergeVisibleOrder(['a', 'b', 'c'], ['a', 'd', 'b'])).toEqual(['a', 'd', 'c', 'b'])
	})

	// The helper removes nothing. A stale id keeps its slot here and the lane
	// sort skips an id it cannot resolve. The server drops ids of deleted
	// labels when the order is saved.
	it('keeps a stale id in place', () => {
		expect(mergeVisibleOrder(['a', 'gone', 'b'], ['b', 'a'])).toEqual(['b', 'gone', 'a'])
	})

	// Label lanes are stored as numbers. 12 and "12" are different entries, so
	// the stored "12" keeps its slot and the visible 12 and 7 are filled in behind it.
	it('treats 12 and "12" as different entries', () => {
		expect(mergeVisibleOrder(['12', 7], [12, 7])).toEqual(['12', 12, 7])
	})

	it('drops repeated entries of the stored order', () => {
		expect(mergeVisibleOrder([5, 5, 1], [1])).toEqual([5, 1])
		expect(mergeVisibleOrder([1, 1], [1])).toEqual([1])
	})

	it('does not mutate the stored array', () => {
		const stored = ['a', 'b', 'c']
		mergeVisibleOrder(stored, ['c', 'a'])
		expect(stored).toEqual(['a', 'b', 'c'])
	})
})
