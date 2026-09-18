/**
 * SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import moment from '@nextcloud/moment'

const maxYear = 9999

export default {
	computed: {
		formatReadableDate() {
			return (timestamp) => {
				return moment(timestamp).format('lll')
			}
		},
	},
	methods: {
		isValidDate(val) {
			// Prevent setting a date with a year greater than 9999, as this can cause issues with some date libraries and databases.
			const date = val ? new Date(val) : null
			return !(date && date.getFullYear() > maxYear)
		},
	},
}
