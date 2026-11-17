/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { afterEach, beforeEach, describe, expect, it, jest } from '@jest/globals'
import { getClient, getRootPath, resultToNode } from '@nextcloud/files/dav'
import { generateUrl } from '@nextcloud/router'
import { canView, getViewer } from '@nextcloud/viewer'
import AttachmentList from './AttachmentList.vue'
import logger from '../../logger.js'

jest.mock('@nextcloud/auth', () => ({ getCurrentUser: jest.fn() }), { virtual: true })
jest.mock('@nextcloud/axios', () => ({}), { virtual: true })
// The component calls the global `t` at import time, right after importing the dialogs
jest.mock('@nextcloud/dialogs', () => {
	global.t = (app, text) => text
	return {
		getFilePickerBuilder: () => {
			const builder = {
				setMultiSelect: () => builder,
				setType: () => builder,
				allowDirectories: () => builder,
				build: () => ({}),
			}
			return builder
		},
	}
}, { virtual: true })
jest.mock('@nextcloud/files', () => ({ formatFileSize: jest.fn() }), { virtual: true })
jest.mock('@nextcloud/files/dav', () => ({
	getClient: jest.fn(),
	getDefaultPropfind: jest.fn(() => '<propfind />'),
	getRootPath: jest.fn(() => '/files/alice'),
	resultToNode: jest.fn(),
}), { virtual: true })
jest.mock('@nextcloud/initial-state', () => ({ loadState: jest.fn(() => -1) }), { virtual: true })
jest.mock('@nextcloud/router', () => ({
	generateOcsUrl: jest.fn(),
	generateRemoteUrl: jest.fn(),
	generateUrl: jest.fn((url) => `/index.php${url}`),
}), { virtual: true })
jest.mock('@nextcloud/viewer', () => ({ canView: jest.fn(), getViewer: jest.fn() }), { virtual: true })
jest.mock('@nextcloud/vue', () => ({}), { virtual: true })
jest.mock('pinia', () => ({ mapActions: () => ({}), mapState: () => ({}) }))
jest.mock('../AttachmentDragAndDrop.vue', () => ({}))
jest.mock('../../logger.js', () => ({ __esModule: true, default: { error: jest.fn() } }))
jest.mock('../../mixins/attachmentUpload.js', () => ({}))
jest.mock('../../mixins/relativeDate.js', () => ({}))
jest.mock('../../stores/attachment.js', () => ({}))
jest.mock('../../stores/board.js', () => ({}))
jest.mock('../../stores/settings.js', () => ({}))

const originalWindow = global.window
const originalDocument = global.document

let session
let sidebar
let stat

const attachment = {
	id: 3,
	cardId: 20,
	extendedData: { fileid: 42, path: '/Deck/photo.jpg', mimetype: 'image/jpeg' },
}

function context(cardDetailsInModal = false) {
	return {
		cardDetailsInModal,
		fetchAttachmentNode(item) {
			return AttachmentList.methods.fetchAttachmentNode.call(this, item)
		},
	}
}

beforeEach(() => {
	jest.clearAllMocks()
	global.window = { location: '' }
	sidebar = { style: { display: '' } }
	global.document = { querySelector: jest.fn(() => sidebar) }

	session = new EventTarget()
	getViewer.mockReturnValue({ open: jest.fn().mockResolvedValue(session) })
	stat = jest.fn().mockResolvedValue({ data: { filename: '/files/alice/Deck/photo.jpg' } })
	getClient.mockReturnValue({ stat })
	resultToNode.mockImplementation(() => ({ fileid: 42, mime: 'image/jpeg' }))
})

afterEach(() => {
	global.window = originalWindow
	global.document = originalDocument
})

describe('opening attachments', () => {
	it('opens a viewable file attachment in the viewer', async () => {
		canView.mockReturnValue(true)

		await AttachmentList.methods.showViewer.call(context(), attachment)

		expect(getRootPath).toHaveBeenCalled()
		expect(stat).toHaveBeenCalledWith('/files/alice/Deck/photo.jpg', { details: true, data: '<propfind />' })
		const node = resultToNode.mock.results[0].value
		expect(canView).toHaveBeenCalledWith(node)
		expect(getViewer().open).toHaveBeenCalledWith([node], node)
		expect(global.window.location).toBe('')
	})

	it('hides the card sidebar while the viewer is open from the modal', async () => {
		canView.mockReturnValue(true)

		await AttachmentList.methods.showViewer.call(context(true), attachment)
		expect(sidebar.style.display).toBe('none')

		session.dispatchEvent(new Event('close'))
		expect(sidebar.style.display).toBe('')
	})

	it('navigates to the file when the viewer cannot open it', async () => {
		canView.mockReturnValue(false)

		await AttachmentList.methods.showViewer.call(context(), attachment)

		expect(getViewer().open).not.toHaveBeenCalled()
		expect(generateUrl).toHaveBeenCalledWith('/f/42')
		expect(global.window.location).toBe('/index.php/f/42')
	})

	it('navigates to the file when it cannot be loaded', async () => {
		const error = new Error('Not found')
		stat.mockRejectedValue(error)

		await AttachmentList.methods.showViewer.call(context(), attachment)

		expect(logger.error).toHaveBeenCalledWith(expect.any(String), { error })
		expect(canView).not.toHaveBeenCalled()
		expect(global.window.location).toBe('/index.php/f/42')
	})

	it('downloads attachments that are not files', async () => {
		await AttachmentList.methods.showViewer.call(context(), { id: 3, cardId: 20, extendedData: {} })

		expect(stat).not.toHaveBeenCalled()
		expect(global.window.location).toBe('/index.php/apps/deck/cards/20/attachment/3')
	})
})
