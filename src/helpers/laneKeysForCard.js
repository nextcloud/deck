/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { SWIMLANE_NONE_KEY } from '../constants.js'

/**
 * The stored lane entry of a label, its id.
 *
 * @param {object} label the label
 * @return {number}
 */
export function labelLaneKey(label) {
	return label.id
}

/**
 * The stored lane entry of an assignee, "<type>:<uid>". The type keeps a user
 * and a group with the same uid apart. The server checks the same format.
 *
 * @param {number} type the participant type of the assignment
 * @param {string} uid the participant uid
 * @return {string}
 */
export function assigneeLaneKey(type, uid) {
	return type + ':' + uid
}

/**
 * The lane keys of a card. The only place that decides where a card goes.
 *
 * Assignee keys carry the participant type, a user and a group can share a uid.
 * A card without a resolvable label or assignee goes to the catch-all lane.
 *
 * @param {object} card the card to place
 * @param {string} mode 'labels' or 'assignees'
 * @return {Array<number|string>} lane keys, never empty
 */
export default function laneKeysForCard(card, mode) {
	const keys = []
	if (mode === 'labels') {
		if (!card.labels || card.labels.length === 0) {
			keys.push(SWIMLANE_NONE_KEY)
			return keys
		}
		for (const label of card.labels) {
			keys.push(labelLaneKey(label))
		}
		return keys
	}
	if (card.assignedUsers) {
		for (const user of card.assignedUsers) {
			const uid = user?.participant?.uid
			if (uid) {
				keys.push(assigneeLaneKey(user.type, uid))
			}
		}
	}
	if (keys.length === 0) {
		keys.push(SWIMLANE_NONE_KEY)
	}
	return keys
}
