/**
 * SPDX-FileCopyrightText: 2018 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
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
