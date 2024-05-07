/**
 * SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import moment from '@nextcloud/moment'

export default {
	computed: {
		formatReadableDate() {
			return (timestamp) => {
				return moment(timestamp).format('lll')
			}
		},
	},
}
