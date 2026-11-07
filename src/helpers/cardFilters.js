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
