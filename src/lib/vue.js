/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import clickOutside from '../directives/clickOutside.js'
import focus from '../directives/focus.js'
import { showError } from '../helpers/dialogs.js'

const configuredConstructors = new WeakSet()
const pendingAppConfigurations = new WeakMap()

function getVueModule(Vue) {
	return Vue?.default || Vue
}

function isModernVueApi(Vue) {
	return typeof getVueModule(Vue)?.createApp === 'function'
}

function toVue3EventProps(on = {}) {
	return Object.fromEntries(Object.entries(on).map(([eventName, handler]) => {
		const normalizedEventName = eventName.charAt(0).toUpperCase() + eventName.slice(1)
		return [`on${normalizedEventName}`, handler]
	}))
}

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

export function configureDeckVue(Vue, {
	translate,
	translatePlural,
	oc,
	oca,
	installCommonDirectives = false,
	installErrorHandler = false,
} = {}) {
	const vueModule = getVueModule(Vue)
	const options = {
		translate,
		translatePlural,
		oc,
		oca,
		installCommonDirectives,
		installErrorHandler,
	}

	if (isModernVueApi(vueModule)) {
		pendingAppConfigurations.set(vueModule, options)
		return vueModule
	}

	if (translate) {
		vueModule.prototype.t = translate
	}

	if (translatePlural) {
		vueModule.prototype.n = translatePlural
	}

	if (oc !== undefined) {
		vueModule.prototype.OC = oc
	}

	if (oca !== undefined) {
		vueModule.prototype.OCA = oca
	}

	if (installCommonDirectives && !configuredConstructors.has(vueModule)) {
		vueModule.directive('click-outside', clickOutside)
		vueModule.directive('focus', focus)
		configuredConstructors.add(vueModule)
	}

	if (installErrorHandler) {
		vueModule.config.errorHandler = createErrorHandler(translate)
	}

	return vueModule
}

export function createRenderFunction(Vue, Component, {
	props = {},
	on = {},
} = {}) {
	const vueModule = getVueModule(Vue)

	if (isModernVueApi(vueModule)) {
		return () => vueModule.h(Component, {
			...props,
			...toVue3EventProps(on),
		})
	}

	return (createElement) => createElement(Component, { props, on })
}

export function mountVueRoot(Vue, options, target = null) {
	const vueModule = getVueModule(Vue)

	if (isModernVueApi(vueModule)) {
		const { router, store, ...appOptions } = options
		const app = vueModule.createApp(appOptions)
		applyDeckAppConfiguration(app, pendingAppConfigurations.get(vueModule))

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

	const VueConstructor = vueModule
	const root = new VueConstructor(options)
	const mountedRoot = target ? root.$mount(target) : root.$mount()
	return {
		app: null,
		root: mountedRoot,
		element: mountedRoot.$el,
		destroy() {
			mountedRoot.$destroy()
		},
	}
}
