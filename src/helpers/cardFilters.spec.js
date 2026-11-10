/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { describe, expect, it } from '@jest/globals'
import { cardCreatedByAny, cardCreatorUsers, DELETED_CREATORS, isCreatorDeleted } from './cardFilters.js'

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

describe('cardCreatorUsers', () => {
	const alice = { uid: 'alice', displayname: 'Alice' }
	const bob = { uid: 'bob', displayname: 'Bob' }
	const carol = { uid: 'carol', displayname: 'Carol' }

	it('includes creators that are no longer board users', () => {
		const cards = [{ owner: carol }, { owner: alice }]
		expect(cardCreatorUsers(cards, [alice, bob])).toEqual([alice, bob, carol])
	})

	it('de-duplicates users by uid', () => {
		const cards = [{ owner: { ...alice } }, { owner: alice }, { owner: bob }]
		expect(cardCreatorUsers(cards, [alice])).toEqual([alice, bob])
	})

	it('sorts users by display name', () => {
		const cards = [{ owner: carol }, { owner: bob }, { owner: alice }]
		expect(cardCreatorUsers(cards).map((user) => user.uid)).toEqual(['alice', 'bob', 'carol'])
	})

	it('skips removed users without display name', () => {
		const cards = [{ owner: { uid: 'removed', displayname: null } }, { owner: alice }]
		expect(cardCreatorUsers(cards, [])).toEqual([alice])
	})

	it('skips cards without owner object', () => {
		const cards = [{ owner: null }, {}, { owner: 'bob' }]
		expect(cardCreatorUsers(cards, [alice])).toEqual([alice])
	})
})

describe('deleted creators', () => {
	const boardUsers = [{ uid: 'alice', displayname: 'Alice' }]

	it('treats a plain uid owner outside the board users as deleted', () => {
		expect(isCreatorDeleted({ owner: 'gone' }, boardUsers)).toBe(true)
	})

	it('does not treat a plain uid owner of a board user as deleted', () => {
		expect(isCreatorDeleted({ owner: 'alice' }, boardUsers)).toBe(false)
	})

	it('does not treat resolved owners as deleted, even without board access', () => {
		expect(isCreatorDeleted({ owner: { uid: 'bob', displayname: 'Bob' } }, boardUsers)).toBe(false)
	})

	it('treats a resolved owner without display name as deleted', () => {
		expect(isCreatorDeleted({ owner: { uid: 'gone', displayname: null } }, boardUsers)).toBe(true)
	})

	it('does not treat cards without owner as deleted', () => {
		expect(isCreatorDeleted({ owner: null }, boardUsers)).toBe(false)
		expect(isCreatorDeleted({}, boardUsers)).toBe(false)
	})

	it('matches all deleted creators with the DELETED_CREATORS value', () => {
		expect(cardCreatedByAny({ owner: 'gone' }, [DELETED_CREATORS], boardUsers)).toBe(true)
		expect(cardCreatedByAny({ owner: 'other-gone' }, [DELETED_CREATORS], boardUsers)).toBe(true)
		expect(cardCreatedByAny({ owner: 'alice' }, [DELETED_CREATORS], boardUsers)).toBe(false)
		expect(cardCreatedByAny({ owner: { uid: 'bob', displayname: 'Bob' } }, [DELETED_CREATORS], boardUsers)).toBe(false)
	})

	it('combines DELETED_CREATORS with selected users', () => {
		const uids = [DELETED_CREATORS, 'bob']
		expect(cardCreatedByAny({ owner: 'gone' }, uids, boardUsers)).toBe(true)
		expect(cardCreatedByAny({ owner: { uid: 'bob', displayname: 'Bob' } }, uids, boardUsers)).toBe(true)
		expect(cardCreatedByAny({ owner: 'alice' }, uids, boardUsers)).toBe(false)
	})
})
