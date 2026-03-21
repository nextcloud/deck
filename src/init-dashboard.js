/**
 * SPDX-FileCopyrightText: 2019 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import './css/dashboard.scss'

import './shared-init.js'
import { mountComponent } from './lib/mountComponent.js'

const debug = process.env.NODE_ENV !== 'production'

let _imports = null

const getAsyncImports = async () => {
	if (_imports) {
		return _imports
	}

	const { default: Vue } = await import('vue')
	const { default: Vuex } = await import('vuex')
	const { default: dashboard } = await import('./store/dashboard.js')

	Vue.prototype.t = t
	Vue.prototype.n = n
	Vue.prototype.OC = OC
	Vue.use(Vuex)

	const store = new Vuex.Store({
		modules: {
			dashboard,
		},
		strict: debug,
	})

	_imports = {
		store, Vue,
	}

	return _imports
}

document.addEventListener('DOMContentLoaded', () => {
	OCA.Dashboard.register('deck', async (el) => {
		const { Vue, store } = await getAsyncImports()
		const { default: DashboardUpcoming } = await import('./views/DashboardUpcoming.vue')

		return mountComponent(Vue, DashboardUpcoming, {
			target: el,
			store,
		}).root
	})

	OCA.Dashboard.register('deckToday', async (el) => {
		const { Vue, store } = await getAsyncImports()
		const { default: DashboardToday } = await import('./views/DashboardToday.vue')
		return mountComponent(Vue, DashboardToday, {
			target: el,
			store,
		}).root
	})

	OCA.Dashboard.register('deckTomorrow', async (el) => {
		const { Vue, store } = await getAsyncImports()
		const { default: DashboardTomorrow } = await import('./views/DashboardTomorrow.vue')
		return mountComponent(Vue, DashboardTomorrow, {
			target: el,
			store,
		}).root
	})
})
