/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { describe, expect, it } from '@jest/globals'
import { SWIMLANE_NONE_KEY } from '../constants.js'
import laneKeysForCard, { assigneeLaneKey, labelLaneKey } from './laneKeysForCard.js'

const user = (uid, type = 0) => ({ type, participant: { uid, displayname: uid } })
// AssignmentMapper::getOrigin returns null once the user, group or circle
// behind an assignment is gone, so this shape arrives from the API.
const brokenUser = (type = 0) => ({ type, participant: null })

// The same vectors are in BoardServiceTest::testSwimlaneAssigneeKey.
describe('lane key helpers', () => {
	it('uses the label id as a number', () => {
		expect(labelLaneKey({ id: 12 })).toBe(12)
	})

	it.each([
		[0, 'admin', '0:admin'],
		[1, 'Sales/EU', '1:Sales/EU'],
		[6, 'bob@cloud.example:8443', '6:bob@cloud.example:8443'],
		[1, 'a:b"c', '1:a:b"c'],
	])('builds the assignee key for %s and %s', (type, uid, expected) => {
		expect(assigneeLaneKey(type, uid)).toBe(expected)
	})
})

describe('laneKeysForCard', () => {
	describe('labels', () => {
		it('places a card in one lane per label', () => {
			const card = { labels: [{ id: 3 }, { id: 7 }] }
			expect(laneKeysForCard(card, 'labels')).toEqual([3, 7])
		})

		it('places a card without labels in the catch-all lane', () => {
			expect(laneKeysForCard({ labels: [] }, 'labels')).toEqual([SWIMLANE_NONE_KEY])
			expect(laneKeysForCard({}, 'labels')).toEqual([SWIMLANE_NONE_KEY])
		})
	})

	describe('assignees', () => {
		it('places a card in one lane per assignee', () => {
			const card = { assignedUsers: [user('alice'), user('bob')] }
			expect(laneKeysForCard(card, 'assignees')).toEqual(['0:alice', '0:bob'])
		})

		// A group and a user may carry the same name, and Nextcloud keeps them
		// in separate namespaces. Without the type in the key both would end up
		// in one lane.
		it('separates a group from a user of the same name', () => {
			const card = { assignedUsers: [user('team', 0), user('team', 1)] }
			expect(laneKeysForCard(card, 'assignees')).toEqual(['0:team', '1:team'])
		})

		it('skips an assignee that no longer resolves', () => {
			const card = { assignedUsers: [user('alice'), brokenUser()] }
			expect(laneKeysForCard(card, 'assignees')).toEqual(['0:alice'])
		})

		// The case that made a card disappear from the board: every assignee
		// unresolvable used to yield no lane at all, and the catch-all lane was
		// only offered to cards with an empty assignee list.
		it('places a card whose assignees all fail to resolve in the catch-all lane', () => {
			const card = { assignedUsers: [brokenUser(), brokenUser(1)] }
			expect(laneKeysForCard(card, 'assignees')).toEqual([SWIMLANE_NONE_KEY])
		})

		it('places an unassigned card in the catch-all lane', () => {
			expect(laneKeysForCard({ assignedUsers: [] }, 'assignees')).toEqual([SWIMLANE_NONE_KEY])
			expect(laneKeysForCard({}, 'assignees')).toEqual([SWIMLANE_NONE_KEY])
		})
	})

	// The badge counts off this function, so a card is never promised a lane it
	// does not reach.
	it('never returns an empty list', () => {
		const cards = [
			{},
			{ labels: [], assignedUsers: [] },
			{ assignedUsers: [brokenUser()] },
		]
		for (const card of cards) {
			expect(laneKeysForCard(card, 'labels').length).toBeGreaterThan(0)
			expect(laneKeysForCard(card, 'assignees').length).toBeGreaterThan(0)
		}
	})
})
