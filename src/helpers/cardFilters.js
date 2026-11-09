/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

/**
 * Check whether a card was created by one of the given users.
 * The card owner is either a user id or a user object.
 *
 * @param {object} card the card to check
 * @param {string[]} uids the user ids to match against
 * @return {boolean}
 */
export function cardCreatedByAny(card, uids) {
	const ownerUid = card.owner?.uid ?? card.owner
	return uids.includes(ownerUid)
}

/**
 * Collect the users that can be picked in the "Filter by creator" list:
 * the creators of the given cards merged with the given board users,
 * de-duplicated by uid and sorted by display name. Users without a
 * display name (e.g. removed accounts) are skipped.
 *
 * @param {object[]} cards the cards of the board
 * @param {object[]} boardUsers the users the board is shared with
 * @return {object[]}
 */
export function cardCreatorUsers(cards, boardUsers = []) {
	const users = new Map()
	for (const user of [...boardUsers, ...cards.map((card) => card.owner)]) {
		if (user?.uid && user.displayname && !users.has(user.uid)) {
			users.set(user.uid, user)
		}
	}
	return [...users.values()].sort((a, b) => a.displayname.localeCompare(b.displayname))
}
