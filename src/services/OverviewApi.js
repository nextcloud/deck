/*
 * @copyright Copyright (c) 2020 Jakob Röhrl <jakob.roehrl@web.de>
 *
 * @author Jakob Röhrl <jakob.roehrl@web.de>
 *
 * @license GNU AGPL version 3 or any later version
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 *
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
				(err) => Promise.reject(err)
			)
			.catch((err) => Promise.reject(err)
			)
	}

}
