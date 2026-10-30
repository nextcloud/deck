/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { describe, expect, it } from '@jest/globals'
import { cardCreatedByAny } from './cardFilters.js'

describe('cardCreatedByAny', () => {
	it('matches an owner given as user id', () => {
		expect(cardCreatedByAny({ owner: 'alice' }, ['alice'])).toBe(true)
	})

	it('matches an owner given as user object', () => {
		expect(cardCreatedByAny({ owner: { uid: 'alice', displayname: 'Alice' } }, ['alice'])).toBe(true)
	})

	it('matches if the owner is any of several users', () => {
		expect(cardCreatedByAny({ owner: 'bob' }, ['alice', 'bob'])).toBe(true)
		expect(cardCreatedByAny({ owner: { uid: 'bob' } }, ['alice', 'bob'])).toBe(true)
	})

	it('does not match other owners', () => {
		expect(cardCreatedByAny({ owner: 'carol' }, ['alice', 'bob'])).toBe(false)
		expect(cardCreatedByAny({ owner: { uid: 'carol' } }, ['alice', 'bob'])).toBe(false)
	})

	it('does not match a card without owner', () => {
		expect(cardCreatedByAny({ owner: null }, ['alice'])).toBe(false)
		expect(cardCreatedByAny({}, ['alice'])).toBe(false)
	})

	it('does not match when no users are given', () => {
		expect(cardCreatedByAny({ owner: 'alice' }, [])).toBe(false)
	})
})
