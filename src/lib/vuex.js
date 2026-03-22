/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

const configuredConstructors = new WeakSet()

export function configureDeckVuex(Vue, Vuex) {
	if (!configuredConstructors.has(Vue)) {
		Vue.use(Vuex)
		configuredConstructors.add(Vue)
	}

	return Vuex
}

export function createDeckStore(Vuex, options) {
	return new Vuex.Store(options)
}