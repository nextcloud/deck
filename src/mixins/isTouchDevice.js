/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

export default {
	computed: {
		isTouchDevice() {
			return ('ontouchstart' in window) || (navigator.maxTouchPoints > 0)
		},
	},
}
