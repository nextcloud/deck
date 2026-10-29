/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { afterEach, beforeEach, describe, expect, it, jest } from '@jest/globals'
import { reactive } from 'vue'
import Description from './Description.vue'
import logger from '../../logger.js'

jest.mock('@nextcloud/dialogs', () => ({ showWarning: jest.fn() }), { virtual: true })
jest.mock('@nextcloud/files', () => ({ formatFileSize: jest.fn() }), { virtual: true })
jest.mock('@nextcloud/router', () => ({ generateUrl: jest.fn() }), { virtual: true })
jest.mock('@nextcloud/vue', () => ({}), { virtual: true })
jest.mock('pinia', () => ({ mapActions: () => ({}), mapState: () => ({}) }))
jest.mock('vue-easymde', () => ({}))
jest.mock('vue-material-design-icons/Paperclip.vue', () => ({}))
jest.mock('./AttachmentList.vue', () => ({}))
jest.mock('../../logger.js', () => ({ __esModule: true, default: { error: jest.fn() } }))
jest.mock('../../stores/card.js', () => ({}))
jest.mock('../../stores/settings.js', () => ({}))

const originalWindow = global.window

function context(collaborative = true) {
	return reactive({
		collaborative,
		canEdit: true,
		card: { id: 20, description: 'Existing description' },
		$refs: { editor: {} },
		editor: null,
		disposed: false,
		descriptionSaveTimeout: null,
		saveDescription: jest.fn().mockResolvedValue(),
		updateDescription: jest.fn(),
		destroyEditor() {
			return Description.methods.destroyEditor.call(this)
		},
	})
}

beforeEach(() => {
	jest.clearAllMocks()
	global.window = { OCA: { Text: {
		createCollaborativeEditor: jest.fn(),
		createMarkdownContentEditor: jest.fn(),
	} } }
})

afterEach(() => {
	global.window = originalWindow
})

describe('description editor lifecycle', () => {
	it.each([true, false])('keeps Text instances with private fields out of Vue reactivity (collaborative: %s)', async (collaborative) => {
		class Editor {
			#destroyed = false
			destroy() {
				this.#destroyed = true
			}

			get destroyed() {
				return this.#destroyed
			}
		}
		const editor = new Editor()
		const description = context(collaborative)
		window.OCA.Text.createCollaborativeEditor.mockResolvedValue(editor)
		window.OCA.Text.createMarkdownContentEditor.mockResolvedValue(editor)

		await Description.methods.setupEditor.call(description)
		expect(description.editor).toBe(editor)
		await Description.beforeUnmount.call(description)
		expect(editor.destroyed).toBe(true)
		expect(description.editor).toBeNull()
		expect(logger.error).not.toHaveBeenCalled()
	})

	it('passes the card context and permissions to Text', async () => {
		const description = context()
		description.canEdit = false
		window.OCA.Text.createCollaborativeEditor.mockResolvedValue({ destroy: jest.fn() })
		await Description.methods.setupEditor.call(description)
		expect(window.OCA.Text.createCollaborativeEditor).toHaveBeenCalledWith({
			context: { type: 'deck_card', id: 20 },
			el: description.$refs.editor,
			readOnly: true,
		})
	})

	it('does not create an editor after unmount during initial cleanup', async () => {
		const description = context()
		const setup = Description.methods.setupEditor.call(description)
		await Description.beforeUnmount.call(description)
		await setup
		expect(window.OCA.Text.createCollaborativeEditor).not.toHaveBeenCalled()
	})

	it.each([true, false])('destroys an editor resolved after unmount (collaborative: %s)', async (collaborative) => {
		let resolveEditor
		const loading = new Promise((resolve) => {
			resolveEditor = resolve
		})
		window.OCA.Text.createCollaborativeEditor.mockReturnValue(loading)
		window.OCA.Text.createMarkdownContentEditor.mockReturnValue(loading)
		const description = context(collaborative)
		const setup = Description.methods.setupEditor.call(description)
		await Promise.resolve()
		await Promise.resolve()
		await Description.beforeUnmount.call(description)
		const editor = { destroy: jest.fn() }
		resolveEditor(editor)
		await setup
		expect(editor.destroy).toHaveBeenCalledTimes(1)
		expect(description.editor).toBeNull()
	})

	it('destroys an existing instance only once', async () => {
		const description = context()
		const editor = { destroy: jest.fn() }
		description.editor = editor
		await description.destroyEditor()
		await description.destroyEditor()
		expect(editor.destroy).toHaveBeenCalledTimes(1)
	})

	it('saves pending non-collaborative content before destroying the editor', async () => {
		const description = context(false)
		const destroy = jest.fn(() => expect(description.saveDescription).toHaveBeenCalled())
		description.editor = { destroy }
		await description.destroyEditor()
		expect(destroy).toHaveBeenCalledTimes(1)
	})

	it('reports editor destruction failures', async () => {
		const description = context()
		const error = new Error('Unmount failed')
		description.editor = { destroy: jest.fn(() => {
			throw error
		}) }
		await description.destroyEditor()
		expect(logger.error).toHaveBeenCalledWith('Error destroying text editor', { error })
		expect(description.editor).toBeNull()
	})
})
