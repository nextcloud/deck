/*
 * @copyright Copyright (c) 2022, chandi Langecker (git@chandi.it)
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
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 */

import axios from '@nextcloud/axios'
import { generateOcsUrl } from '@nextcloud/router'

export class SessionApi {

	url(url) {
		return generateOcsUrl(`apps/deck/api/v1.0${url}`)
	}

	async createSession(boardId) {
		return (await axios.put(this.url('/session/create'), { boardId })).data.ocs.data
	}

	async syncSession(boardId, token) {
		return await axios.post(this.url('/session/sync'), { boardId, token })
	}

	async closeSession(boardId, token) {
		return await axios.post(this.url('/session/close'), { boardId, token })
	}

	async closeSessionViaBeacon(boardId, token) {
		const body = {
			boardId,
			token,
		}
		const headers = {
			type: 'application/json',
		}
		const blob = new Blob([JSON.stringify(body)], headers)
		navigator.sendBeacon(this.url('/session/close'), blob)
	}

}

export const sessionApi = new SessionApi()
