/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import axios from '@nextcloud/axios'
import { AttachmentApi } from './AttachmentApi.js'

jest.mock('@nextcloud/axios', () => ({
	__esModule: true,
	default: jest.fn(),
}))

jest.mock('@nextcloud/router', () => ({
	__esModule: true,
	generateUrl: jest.fn((url) => `/index.php${url}`),
	generateOcsUrl: jest.fn((url) => `/ocs/v2.php${url.startsWith('/') ? url : '/' + url}`),
}))

describe('AttachmentApi', () => {
	const api = new AttachmentApi()
	const attachment = { id: 3, cardId: 7, type: 'deck_file' }
	const boardId = 5

	beforeEach(() => {
		axios.mockReset()
		axios.mockResolvedValue({ data: { ocs: { data: {} } } })
	})

	it('fetches attachments from the registered OCS route', async () => {
		await api.fetchAttachments(7, boardId)

		expect(axios).toHaveBeenCalledWith(expect.objectContaining({
			method: 'GET',
			url: '/ocs/v2.php/apps/deck/api/v1.0/cards/7/attachments',
			params: { boardId },
		}))
	})

	it('creates an attachment through the registered OCS route', async () => {
		const formData = { fake: 'formData' }
		await api.createAttachment({ cardId: 7, formData, onUploadProgress: undefined, boardId })

		expect(axios).toHaveBeenCalledWith(expect.objectContaining({
			method: 'POST',
			url: '/ocs/v2.php/apps/deck/api/v1.0/cards/7/attachment',
			params: { boardId },
			data: formData,
		}))
	})

	it('deletes an attachment through the registered OCS route', async () => {
		await api.deleteAttachment(attachment, boardId)

		expect(axios).toHaveBeenCalledWith(expect.objectContaining({
			method: 'DELETE',
			url: '/ocs/v2.php/apps/deck/api/v1.0/cards/7/attachments/3',
			params: { type: 'deck_file', boardId },
		}))
	})

	it('updates an attachment through the registered OCS route', async () => {
		const updated = { id: 3, cardId: 7, type: 'deck_file', data: 'file.png' }
		axios.mockResolvedValue({ data: { ocs: { data: updated } } })
		const formData = { fake: 'formData' }
		const result = await api.updateAttachment({ cardId: 7, attachment, formData, boardId })
		expect(result).toEqual(updated)

		expect(axios).toHaveBeenCalledWith(expect.objectContaining({
			method: 'POST',
			url: '/ocs/v2.php/apps/deck/api/v1.0/cards/7/attachments/3',
			params: { type: 'deck_file', boardId },
			data: formData,
		}))
	})

	it('restores an attachment through the registered OCS route', async () => {
		await api.restoreAttachment(attachment, boardId)

		expect(axios).toHaveBeenCalledWith(expect.objectContaining({
			method: 'PUT',
			url: '/ocs/v2.php/apps/deck/api/v1.0/cards/7/attachments/3/restore',
			params: { type: 'deck_file', boardId },
		}))
	})
})
