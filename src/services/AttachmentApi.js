/**
 * SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import axios from '@nextcloud/axios'
import { generateOcsUrl, generateUrl } from '@nextcloud/router'

export class AttachmentApi {

	url(url) {
		return generateUrl(`/apps/deck${url}`)
	}

	ocsUrl(url) {
		url = `/apps/deck/api/v1.0${url}`
		return generateOcsUrl(url)
	}

	async fetchAttachments(cardId, boardId) {
		const response = await axios({
			method: 'GET',
			url: this.ocsUrl(`/cards/${cardId}/attachments`),
			params: {
				boardId: boardId ?? null,
			},
		})
		return response.data.ocs.data
	}

	async createAttachment({ cardId, formData, onUploadProgress, boardId }) {
		const response = await axios({
			method: 'POST',
			url: this.ocsUrl(`/cards/${cardId}/attachment`),
			params: {
				boardId: boardId ?? null,
			},
			data: formData,
			onUploadProgress,
		})
		return response.data.ocs.data
	}

	async updateAttachment({ cardId, attachment, formData, boardId }) {
		// POST instead of PUT as multipart/form-data bodies are only parsed by PHP for POST requests
		const response = await axios({
		   method: 'POST',
		   url: this.ocsUrl(`/cards/${cardId}/attachments/${attachment.id}`),
		   params: {
				type: attachment.type,
				boardId: boardId ?? null,
		   },
		   data: formData,
	   })
	   return response.data.ocs.data
	}

	async deleteAttachment(attachment, boardId) {
		await axios({
			method: 'DELETE',
			url: this.ocsUrl(`/cards/${attachment.cardId}/attachments/${attachment.id}`),
			params: {
				type: attachment.type,
				boardId: boardId ?? null,
			},
		})
	}

	async restoreAttachment(attachment, boardId) {
		const response = await axios({
			method: 'PUT',
			url: this.ocsUrl(`/cards/${attachment.cardId}/attachments/${attachment.id}/restore`),
			params: {
				type: attachment.type,
				boardId: boardId ?? null,
			},
		})
		return response.data.ocs.data
	}

}
