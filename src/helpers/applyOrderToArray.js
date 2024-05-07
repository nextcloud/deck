/**
 * SPDX-FileCopyrightText: 2018 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

const arrayMove = function(arrayToSort, removedIndex, addedIndex) {
	if (removedIndex === null && addedIndex === null) return arrayToSort

	const result = [...arrayToSort]
	let itemToAdd = arrayToSort[removedIndex]

	if (removedIndex !== null) {
		itemToAdd = result.splice(removedIndex, 1)[0]
	}

	if (addedIndex !== null) {
		result.splice(addedIndex, 0, itemToAdd)
	}
	return result
}

export default arrayMove
