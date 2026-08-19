/**
 * SPDX-FileCopyrightText: 2022 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { registerWidget, registerCustomPickerElement, NcCustomPickerRenderResult } from '@nextcloud/vue'
import { translate, translatePlural } from '@nextcloud/l10n'

import './shared-init.js'

const createVueApp = async (Component, props) => {
	const { createApp } = await import('vue')
	const { createPinia } = await import('pinia')

	const pinia = createPinia()
	const app = createApp(Component, props)

	app.use(pinia)

	app.config.globalProperties.t = translate
	app.config.globalProperties.n = translatePlural
	app.config.globalProperties.OC = window.OC
	app.config.globalProperties.OCA = window.OCA

	app.directive('focus', {
		mounted(el) {
			el.focus()
		},
	})

	return { app, pinia }
}

registerWidget('deck-card', async (el, { richObjectType, richObject, accessible }) => {
	const { default: CardReferenceWidget } = await import('./views/CardReferenceWidget.vue')
	const { app } = await createVueApp(CardReferenceWidget, { richObjectType, richObject, accessible })
	// trick to change the wrapper element size, otherwise it always is 100%
	// which is not very nice with a simple card
	el.parentNode.style['max-width'] = '400px'
	el.parentNode.style['margin-left'] = '0'
	el.parentNode.style['margin-right'] = '0'
	app.mount(el)
})

const boardApps = {}
registerWidget('deck-board', async (el, { richObjectType, richObject, accessible, interactive }) => {
	const { default: BoardReferenceWidget } = await import('./views/BoardReferenceWidget.vue')
	const { app } = await createVueApp(BoardReferenceWidget, { richObjectType, richObject, accessible, interactive })
	boardApps[el] = app
	app.mount(el)
}, (el) => {
	boardApps[el]?.unmount()
	delete boardApps[el]
})

registerWidget('deck-comment', async (el, { richObjectType, richObject, accessible }) => {
	const { default: CommentReferenceWidget } = await import('./views/CommentReferenceWidget.vue')
	const { app } = await createVueApp(CommentReferenceWidget, { richObjectType, richObject, accessible })

	el.parentNode.style['max-width'] = '400px'
	el.parentNode.style['margin-left'] = '0'
	el.parentNode.style['margin-right'] = '0'

	app.mount(el)
})

registerCustomPickerElement('create-new-deck-card', async (el, { providerId, accessible }) => {
	const { default: CreateNewCardCustomPicker } = await import('./views/CreateNewCardCustomPicker.vue')
	const { app } = await createVueApp(CreateNewCardCustomPicker, { providerId, accessible })
	const mountedApp = app.mount(el)
	return new NcCustomPickerRenderResult(mountedApp.$el, app)
}, (el, renderResult) => {
	renderResult.object.unmount()
}, 'normal')
