/**
 * SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { differenceInMilliseconds, formatDistanceToNow } from 'date-fns'

export default {
	computed: {
		relativeDate() {
			return (timestamp) => {
				const diff = differenceInMilliseconds(new Date(this.$root.time), new Date(timestamp))
				if (diff >= 0 && diff < 45000) {
					return t('core', 'seconds ago')
				}
				return formatDistanceToNow(new Date(timestamp), { addSuffix: true })
			}
		},
	},
}
