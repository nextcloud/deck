/**
 * SPDX-FileCopyrightText: 2019 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import './css/dashboard.scss'

import './shared-init.js'

const debug = process.env.NODE_ENV !== 'production'

let _imports = null

const getAsyncImports = async () => {
	if (_imports) {
		return _imports
	}

	const { default: Vue } = await import('vue')
	const { createPinia, PiniaVuePlugin } = await import('pinia')
	const { default: Vuex } = await import('vuex')

	Vue.prototype.t = t
	Vue.prototype.n = n
	Vue.prototype.OC = OC
	const pinia = createPinia()
	Vue.use(PiniaVuePlugin)
	Vue.use(Vuex)
	const store = new Vuex.Store({
		strict: debug,
	})

	_imports = {
		pinia, Vue, store,
	}

	return _imports
}

document.addEventListener('DOMContentLoaded', () => {
	OCA.Dashboard.register('deck', async (el) => {
		const { Vue, pinia, store } = await getAsyncImports()
		const { default: DashboardUpcoming } = await import('./views/DashboardUpcoming.vue')

		const View = Vue.extend(DashboardUpcoming)
		const vm = new View({
			propsData: {},
			pinia,
			store,
		}).$mount(el)
		return vm
	})

	OCA.Dashboard.register('deckToday', async (el) => {
		const { Vue, pinia, store } = await getAsyncImports()
		const { default: DashboardToday } = await import('./views/DashboardToday.vue')
		const View = Vue.extend(DashboardToday)
		const vm = new View({
			propsData: {},
			pinia,
			store,
		}).$mount(el)
		return vm
	})

	OCA.Dashboard.register('deckTomorrow', async (el) => {
		const { Vue, pinia, store } = await getAsyncImports()
		const { default: DashboardTomorrow } = await import('./views/DashboardTomorrow.vue')
		const View = Vue.extend(DashboardTomorrow)
		const vm = new View({
			propsData: {},
			pinia,
			store,
		}).$mount(el)
		return vm
	})
})
