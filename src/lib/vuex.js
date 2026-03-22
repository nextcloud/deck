/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

const configuredConstructors = new WeakSet()

function getVueModule(Vue) {
	return Vue?.default || Vue
}

function getVuexModule(Vuex) {
	return Vuex?.default || Vuex
}

function isModernVuexApi(Vuex) {
	return typeof getVuexModule(Vuex)?.createStore === 'function'
}

export function configureDeckVuex(Vue, Vuex) {
	const vueModule = getVueModule(Vue)

	if (!isModernVuexApi(Vuex) && !configuredConstructors.has(vueModule)) {
		vueModule.use(getVuexModule(Vuex))
		configuredConstructors.add(vueModule)
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