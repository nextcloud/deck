/**
 * SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
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
