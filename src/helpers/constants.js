/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

/**
 * ACL / owner permission types – mirror Acl::PERMISSION_TYPE_* PHP constants.
 * Keep in sync with lib/Db/Acl.php.
 */
export const PERMISSION_TYPE_USER = 0
export const PERMISSION_TYPE_GROUP = 1
export const PERMISSION_TYPE_REMOTE = 6
export const PERMISSION_TYPE_CIRCLE = 7

/**
 * Map from the Nextcloud sharee-picker source identifier to the board ACL type.
 */
export const SOURCE_TO_SHARE_TYPE = {
	users: PERMISSION_TYPE_USER,
	groups: PERMISSION_TYPE_GROUP,
	emails: 4,
	remotes: PERMISSION_TYPE_REMOTE,
	circles: PERMISSION_TYPE_CIRCLE,
	teams: PERMISSION_TYPE_CIRCLE,
}
