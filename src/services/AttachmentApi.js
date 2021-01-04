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
import { generateUrl } from '@nextcloud/router'

export class AttachmentApi {

	url(url) {
		return generateUrl(`/apps/deck${url}`)
	}

	async fetchAttachments(cardId) {
		const response = await axios({
			method: 'GET',
			url: this.url(`/cards/${cardId}/attachments`),
		})
		return response.data
	}

	async createAttachment({ cardId, formData, onUploadProgress }) {
		const response = await axios({
			method: 'POST',
			url: this.url(`/cards/${cardId}/attachment`),
			data: formData,
			onUploadProgress,
		})
		return response.data
	}

	async updateAttachment({ cardId, attachment, formData }) {
		const response = await axios({
		   method: 'POST',
		   url: this.url(`/cards/${cardId}/attachment/${attachment.type}:${attachment.id}`),
		   data: formData,
	   })
	   return response.data
	}

	async deleteAttachment(attachment) {
		await axios({
			method: 'DELETE',
			url: this.url(`/cards/${attachment.cardId}/attachment/${attachment.type}:${attachment.id}`),
		})
	}

	async restoreAttachment(attachment) {
		const response = await axios({
			method: 'GET',
			url: this.url(`/cards/${attachment.cardId}/attachment/${attachment.type}:${attachment.id}/restore`),
		})
		return response.data
	}

	async displayAttachment(attachment) {
		const response = await axios({
			method: 'GET',
			url: this.url(`/cards/${attachment.cardId}/attachment/${attachment.type}:${attachment.id}`),
		})
		return response.data
	}

}
