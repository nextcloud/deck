/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

/**
 * Filter value standing for all cards created by deleted accounts.
 * Contains a colon, which is not allowed in Nextcloud user ids.
 */
export const DELETED_CREATORS = 'deck:deleted-users'

/**
 * Check whether a card was created by an account that no longer exists.
 * The server sends the owner as a user object only for existing accounts,
 * deleted ones come as plain uid. A plain uid of a board user (e.g. a card
 * just created in this session) does not count as deleted.
 *
 * @param {object} card the card to check
 * @param {object[]} boardUsers the users with access to the board
 * @return {boolean}
 */
export function isCreatorDeleted(card, boardUsers = []) {
	const owner = card.owner
	if (!owner) {
		return false
	}
	if (typeof owner === 'string') {
		return !boardUsers.some((user) => user.uid === owner)
	}
	return !owner.displayname
}

/**
 * Check whether a card was created by one of the given users.
 * The card owner is either a user id or a user object. The value
 * DELETED_CREATORS matches cards of all deleted accounts.
 *
 * @param {object} card the card to check
 * @param {string[]} uids the user ids to match against
 * @param {object[]} boardUsers the users with access to the board, needed for DELETED_CREATORS
 * @return {boolean}
 */
export function cardCreatedByAny(card, uids, boardUsers = []) {
	if (uids.includes(DELETED_CREATORS) && isCreatorDeleted(card, boardUsers)) {
		return true
	}
	const ownerUid = card.owner?.uid ?? card.owner
	return uids.includes(ownerUid)
}

/**
 * Collect the users that can be picked in the "Filter by creator" list:
 * the creators of the given cards merged with the given board users,
 * de-duplicated by uid and sorted by display name. Deleted accounts are
 * not listed, see isCreatorDeleted() for grouping them.
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
