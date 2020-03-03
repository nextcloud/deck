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
import { generateUrl } from '@nextcloud/router'
import xmlToTagList from '../helpers/xml'

export class CommentApi {

	url(url) {
		url = `dav/comments/deckCard/${url}`
		return OC.linkToRemote(url)
	}

	async loadComments({ cardId, limit, offset }) {
		const api = await axios.get(generateUrl(`/apps/deck/api/v1.0/boards/0/stacks/0/cards/${cardId}/comments`), {
			headers: { 'OCS-APIRequest': 'true' },
		})
		return api.data
	}

	async fetchComment({ cardId, commentId }) {
		const response = await axios({
			method: 'PROPFIND',
			url: this.url(`${cardId}/${commentId}`),
			data: `<?xml version="1.0"?>
				<d:propfind  xmlns:d="DAV:" xmlns:oc="http://owncloud.org/ns">
					<d:prop>
						<oc:id />
						<oc:message />
						<oc:actorType />
						<oc:actorId />
						<oc:actorDisplayName />
						<oc:creationDateTime />
						<oc:objectType />
						<oc:objectId />
						<oc:isUnread />
						<oc:mentions />
					</d:prop>
				</d:propfind>`,
		})
		return xmlToTagList(response.data)
	}

	async createComment({ cardId, comment, replyTo }) {
		const api = await axios.post(generateUrl(`/apps/deck/api/v1.0/boards/0/stacks/0/cards/${cardId}/comments`), {
			message: `${comment}`,
			parentId: replyTo ? replyTo.id : null,
		})
		return api.data
	}

	async updateComment({ cardId, commentId, comment }) {
		const api = await axios.put(generateUrl(`/apps/deck/api/v1.0/boards/0/stacks/0/cards/${cardId}/comments/${commentId}`), {
			message: `${comment}`,
		})
		return api.data
	}

	async deleteComment({ cardId, commentId }) {
		const api = await axios.delete(generateUrl(`/apps/deck/api/v1.0/boards/0/stacks/0/cards/${cardId}/comments/${commentId}`))
		return api.data
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
