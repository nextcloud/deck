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

	// A label that was deleted, or an assignee removed from every card, keeps
	// its stored slot. The lane sort skips an id it cannot resolve, and the
	// position applies again if the lane comes back.
	it('keeps a stale id in place', () => {
		expect(mergeVisibleOrder(['a', 'gone', 'b'], ['b', 'a'])).toEqual(['b', 'gone', 'a'])
	})

	it('does not mutate the stored array', () => {
		const stored = ['a', 'b', 'c']
		mergeVisibleOrder(stored, ['c', 'a'])
		expect(stored).toEqual(['a', 'b', 'c'])
	})
})
