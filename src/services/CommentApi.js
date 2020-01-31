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
import { getCurrentUser } from '@nextcloud/auth'
import xmlToTagList from '../helpers/xml'

export class CommentApi {

	url(url) {
		url = `dav/comments/deckCard/${url}`
		return OC.linkToRemote(url)
	}

	async loadComments({ cardId, limit, offset }) {
		const response = await axios({
			method: 'REPORT',
			url: this.url(`${cardId}`),
			data: `<?xml version="1.0" encoding="utf-8" ?>
			<oc:filter-comments xmlns:D="DAV:" xmlns:oc="http://owncloud.org/ns">
				<oc:limit>${limit}</oc:limit>
				<oc:offset>${offset}</oc:offset>
			</oc:filter-comments>`,
		})
		return xmlToTagList(response.data)
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

	async createComment({ cardId, comment }) {
		const response = await axios({
			method: 'POST',
			url: this.url(`${cardId}`),
			data: { actorType: 'users', message: `${comment}`, verb: 'comment' },
		})

		const header = response.headers['content-location']
		const headerArray = header.split('/')
		const id = headerArray[headerArray.length - 1]

		const ret = {
			cardId: (cardId).toString(),
			id: id,
			uId: getCurrentUser().uid,
			creationDateTime: (new Date()).toString(),
			message: comment,
		}
		return ret
	}

	async updateComment({ cardId, commentId, comment }) {
		const response = await axios({
			method: 'PROPPATCH',
			url: this.url(`${cardId}/${commentId}`),
			data: `<?xml version="1.0"?>
			<d:propertyupdate xmlns:d="DAV:" xmlns:oc="http://owncloud.org/ns">
				<d:set>
					<d:prop>
						<oc:message>${comment}</oc:message>
					</d:prop>
				</d:set>
			</d:propertyupdate>`,
		})
		return response.data
	}

	async deleteComment({ cardId, commentId }) {
		const response = await axios({
			method: 'DELETE',
			url: this.url(`${cardId}/${commentId}`),
		})
		return response.data
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
