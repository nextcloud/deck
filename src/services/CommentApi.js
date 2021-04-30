/*
 * @copyright Copyright (c) 2018 Michael Weimann <mail@michael-weimann.eu>
 *
 * @author Michael Weimann <mail@michael-weimann.eu>
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
import { generateOcsUrl, generateRemoteUrl } from '@nextcloud/router'

export class CommentApi {

	url(url) {
		url = `dav/comments/deckCard/${url}`
		return generateRemoteUrl(url)
	}

	async loadComments({ cardId, limit, offset }) {
		const api = await axios.get(generateOcsUrl(`apps/deck/api/v1.0/cards/${cardId}/comments`), {
			params: { limit, offset },
			headers: { 'OCS-APIRequest': 'true' },
		})
		return api.data.ocs.data
	}

	async createComment({ cardId, comment, replyTo }) {
		const api = await axios.post(generateOcsUrl(`apps/deck/api/v1.0/cards/${cardId}/comments`), {
			message: `${comment}`,
			parentId: replyTo ? replyTo.id : null,
		})
		return api.data.ocs.data
	}

	async updateComment({ cardId, id, comment }) {
		const api = await axios.put(generateOcsUrl(`apps/deck/api/v1.0/cards/${cardId}/comments/${id}`), {
			message: `${comment}`,
		})
		return api.data.ocs.data
	}

	async deleteComment({ cardId, id }) {
		const api = await axios.delete(generateOcsUrl(`apps/deck/api/v1.0/cards/${cardId}/comments/${id}`))
		return api.data.ocs.data
	}

	async markCommentsAsRead(cardId) {
		const readMarker = (new Date()).toUTCString()
		const response = await axios({
			method: 'PROPPATCH',
			url: this.url(`${cardId}`),
			data: `<?xml version="1.0"?>
				<d:propertyupdate  xmlns:d="DAV:" xmlns:oc="http://owncloud.org/ns">
				  <d:set>
				   <d:prop>
					  <oc:readMarker>${readMarker}</oc:readMarker>
					</d:prop>
				  </d:set>
				</d:propertyupdate>`,
		})
		return response.data
	}

}
