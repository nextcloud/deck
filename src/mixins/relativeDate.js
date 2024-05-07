/**
 * SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import moment from '@nextcloud/moment'

export default {
	computed: {
		relativeDate() {
			return (timestamp) => {
				const diff = moment(this.$root.time).diff(moment(timestamp))
				if (diff >= 0 && diff < 45000) {
					return t('core', 'seconds ago')
				}
				return moment(timestamp).fromNow()
			}
		},
	},
}
