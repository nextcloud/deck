/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { beforeEach, describe, expect, it, jest } from '@jest/globals'
import { registerWidget } from '@nextcloud/vue'
import { createPinia, disposePinia } from 'pinia'
import { createApp } from 'vue'

import './init-reference.js'

jest.mock('@nextcloud/l10n', () => ({ translate: jest.fn(), translatePlural: jest.fn() }))
jest.mock('@nextcloud/vue', () => ({ registerWidget: jest.fn(), registerCustomPickerElement: jest.fn() }), { virtual: true })
jest.mock('./shared-init.js', () => ({}))
jest.mock('./views/BoardReferenceWidget.vue', () => ({}))
jest.mock('vue', () => ({ createApp: jest.fn() }))
jest.mock('pinia', () => ({ createPinia: jest.fn(), disposePinia: jest.fn() }))

const [, render, destroy] = registerWidget.mock.calls.find(([type]) => type === 'deck-board')
const props = { richObjectType: 'deck-board', richObject: { board: { id: 7 } }, accessible: true, interactive: true }

beforeEach(() => {
	createApp.mockReset()
	createPinia.mockReset()
	disposePinia.mockReset()
	global.window = { OC: {}, OCA: {} }
	createPinia.mockImplementation(() => ({}))
	createApp.mockImplementation(() => ({
		use: jest.fn(),
		directive: jest.fn(),
		mount: jest.fn(),
		unmount: jest.fn(),
		config: { globalProperties: {} },
	}))
})

describe('board reference widget registration', () => {
	it('mounts the interactive board with its own Pinia instance', async () => {
		const element = {}
		await render(element, props)
		const app = createApp.mock.results[0].value
		expect(createApp).toHaveBeenCalledWith(expect.anything(), props)
		expect(app.use).toHaveBeenCalledWith(createPinia.mock.results[0].value)
		expect(app.mount).toHaveBeenCalledWith(element)
		destroy(element)
	})

	it('keeps different DOM elements isolated even if their string representations match', async () => {
		const first = {}
		const second = {}
		await render(first, props)
		await render(second, props)
		const firstApp = createApp.mock.results[0].value
		const secondApp = createApp.mock.results[1].value
		destroy(first)
		expect(firstApp.unmount).toHaveBeenCalledTimes(1)
		expect(secondApp.unmount).not.toHaveBeenCalled()
		expect(disposePinia).toHaveBeenCalledWith(createPinia.mock.results[0].value)
		destroy(second)
		expect(secondApp.unmount).toHaveBeenCalledTimes(1)
	})

	it('does not mount if destroyed before the lazy component loads', async () => {
		const element = {}
		const rendering = render(element, props)
		destroy(element)
		await rendering
		expect(createApp).not.toHaveBeenCalled()
	})

	it('does not mount if destroyed while creating the Vue app', async () => {
		const element = {}
		const create = createApp.getMockImplementation()
		createApp.mockImplementationOnce((...args) => {
			const app = create(...args)
			destroy(element)
			return app
		})
		await render(element, props)
		expect(createApp).toHaveBeenCalledTimes(1)
		expect(createApp.mock.results[0].value.mount).not.toHaveBeenCalled()
		expect(disposePinia).toHaveBeenCalledTimes(1)
	})

	it('replaces a mounted app when the same element is rendered again', async () => {
		const element = {}
		await render(element, props)
		const first = createApp.mock.results[0].value
		await render(element, props)
		expect(first.unmount).toHaveBeenCalledTimes(1)
		destroy(element)
		expect(createApp.mock.results[1].value.unmount).toHaveBeenCalledTimes(1)
	})

	it('does not let an older pending render overwrite a replacement', async () => {
		const element = {}
		const first = render(element, props)
		destroy(element)
		const second = render(element, props)
		await Promise.all([first, second])
		expect(createApp).toHaveBeenCalledTimes(1)
		expect(createApp.mock.results[0].value.mount).toHaveBeenCalledTimes(1)
		destroy(element)
	})

	it('makes repeated cleanup harmless', async () => {
		const element = {}
		await render(element, props)
		destroy(element)
		destroy(element)
		expect(createApp.mock.results[0].value.unmount).toHaveBeenCalledTimes(1)
		expect(disposePinia).toHaveBeenCalledTimes(1)
	})

	it('disposes the Pinia instance even if unmount fails', async () => {
		const element = {}
		await render(element, props)
		createApp.mock.results[0].value.unmount.mockImplementation(() => {
			throw new Error('Unmount failed')
		})
		expect(() => destroy(element)).toThrow('Unmount failed')
		expect(disposePinia).toHaveBeenCalledTimes(1)
	})
})
