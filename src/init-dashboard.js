/**
 * SPDX-FileCopyrightText: 2019 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import './css/dashboard.scss'

import './shared-init.js'

let _imports = null

const getAsyncImports = async () => {
	if (_imports) {
		return _imports
	}

	const { createApp } = await import('vue')
	const { createPinia } = await import('pinia')

	const pinia = createPinia()

	_imports = {
		createApp,
		pinia,
	}

	return _imports
}

const mountDashboardWidget = async (el, componentImport) => {
	const { createApp, pinia } = await getAsyncImports()
	const { default: Component } = await componentImport()

	const app = createApp(Component)
	app.use(pinia)
	app.config.globalProperties.t = t
	app.config.globalProperties.n = n
	app.config.globalProperties.OC = OC
	app.config.globalProperties.OCA = OCA

	const vm = app.mount(el)
	if (vm && typeof vm === 'object') {
		vm.$destroy = () => app.unmount()
	}

	return vm
}

document.addEventListener('DOMContentLoaded', () => {
	OCA.Dashboard.register('deck', (el) => mountDashboardWidget(el, () => import('./views/DashboardUpcoming.vue')))

	OCA.Dashboard.register('deckToday', (el) => mountDashboardWidget(el, () => import('./views/DashboardToday.vue')))

	OCA.Dashboard.register('deckTomorrow', (el) => mountDashboardWidget(el, () => import('./views/DashboardTomorrow.vue')))
})
