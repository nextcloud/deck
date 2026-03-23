/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

function isDuplicateNavigationError(error) {
	return error?.name === 'NavigationDuplicated'
		|| error?.message?.includes('Avoided redundant navigation to current location')
}

export function pushRoute(router, location) {
	const navigationResult = router.push(location)
	if (!navigationResult || typeof navigationResult.then !== 'function') {
		return Promise.resolve(navigationResult)
	}

	return navigationResult.catch((error) => {
		if (isDuplicateNavigationError(error)) {
			return error
		}

		throw error
	})
}
