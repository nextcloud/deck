/**
 * SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import axios from '@nextcloud/axios'
import { generateOcsUrl } from '@nextcloud/router'

export class OverviewApi {

	url(url) {
		return generateOcsUrl(`apps/deck/api/v1.0/${url}`)
	}

	get(filter) {
		return axios.get(this.url(`overview/${filter}`), {
			headers: { 'OCS-APIRequest': 'true' },
		})
			.then(
				(response) => Promise.resolve(response.data.ocs.data),
				(err) => Promise.reject(err),
			)
			.catch((err) => Promise.reject(err),
			)
	}

}
