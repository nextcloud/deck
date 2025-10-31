/**
 * SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { format } from 'date-fns'

export default {
	methods: {
		readableDate(timestamp) {
			// timestamp might be a number or an ISO string; new Date handles both
			return format(new Date(timestamp), 'PPp') // localized date + time
		},
	},
}
