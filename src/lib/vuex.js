/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

const configuredConstructors = new WeakSet()

function getVuexModule(Vuex) {
	return Vuex?.default || Vuex
}

function isModernVuexApi(Vuex) {
	return typeof getVuexModule(Vuex)?.createStore === 'function'
}

export function configureDeckVuex(Vue, Vuex) {
	if (!isModernVuexApi(Vuex) && !configuredConstructors.has(Vue)) {
		Vue.use(getVuexModule(Vuex))
		configuredConstructors.add(Vue)
	}

	return Vuex
}

export function createDeckStore(Vuex, options) {
	const vuexModule = getVuexModule(Vuex)
	if (isModernVuexApi(vuexModule)) {
		return vuexModule.createStore(options)
	}

	return new vuexModule.Store(options)
}