/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { createApp, h } from 'vue'
import clickOutside from '../directives/clickOutside.js'
import focus from '../directives/focus.js'
import { showError } from '../helpers/dialogs.js'

let pendingConfiguration = null

function createErrorHandler(translate) {
	return (err) => {
		if (err.response && err.response.data.message) {
			const errorMessage = translate?.('deck', 'Something went wrong') ?? 'Something went wrong'
			showError(`${errorMessage}: ${err.response.data.status} ${err.response.data.message}`)
		}
		console.error(err)
	}
}

function applyDeckAppConfiguration(app, options = {}) {
	const {
		translate,
		translatePlural,
		oc,
		oca,
		installCommonDirectives = false,
		installErrorHandler = false,
	} = options

	if (translate) {
		app.config.globalProperties.t = translate
	}

	if (translatePlural) {
		app.config.globalProperties.n = translatePlural
	}

	if (oc !== undefined) {
		app.config.globalProperties.OC = oc
	}

	if (oca !== undefined) {
		app.config.globalProperties.OCA = oca
	}

	if (installCommonDirectives) {
		app.directive('click-outside', clickOutside)
		app.directive('focus', focus)
	}

	if (installErrorHandler) {
		app.config.errorHandler = createErrorHandler(translate)
	}
}

export function configureDeckVue(_Vue, options = {}) {
	pendingConfiguration = options
}

function toVue3EventProps(on = {}) {
	return Object.fromEntries(Object.entries(on).map(([eventName, handler]) => {
		const normalizedEventName = eventName.charAt(0).toUpperCase() + eventName.slice(1)
		return [`on${normalizedEventName}`, handler]
	}))
}

export function createRenderFunction(_Vue, Component, {
	props = {},
	on = {},
} = {}) {
	return () => h(Component, {
		...props,
		...toVue3EventProps(on),
	})
}

export function mountVueRoot(_Vue, options, target = null) {
	const { router, store, ...appOptions } = options
	const app = createApp(appOptions)
	if (pendingConfiguration) {
		applyDeckAppConfiguration(app, pendingConfiguration)
	}

	if (store) {
		app.use(store)
	}

	if (router) {
		app.use(router)
	}

	const mountTarget = target || document.createElement('div')
	const root = app.mount(mountTarget)
	return {
		app,
		root,
		element: root?.$el || (typeof mountTarget === 'string' ? document.querySelector(mountTarget) : mountTarget),
		destroy() {
			app.unmount()
		},
	}
}
