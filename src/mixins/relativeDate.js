/**
 * SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { useFormatRelativeTime } from '@nextcloud/vue'

export default {
	computed: {
		relativeDate() {
			return (timestamp) => {
				return useFormatRelativeTime(timestamp)
			}
		},
	},
}
