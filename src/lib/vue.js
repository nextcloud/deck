/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import clickOutside from '../directives/clickOutside.js'
import focus from '../directives/focus.js'
import { showError } from '../helpers/dialogs.js'

const configuredConstructors = new WeakSet()

export function configureDeckVue(Vue, {
	translate,
	translatePlural,
	oc,
	oca,
	installCommonDirectives = false,
	installErrorHandler = false,
} = {}) {
	if (translate) {
		Vue.prototype.t = translate
	}

	if (translatePlural) {
		Vue.prototype.n = translatePlural
	}

	if (oc !== undefined) {
		Vue.prototype.OC = oc
	}

	if (oca !== undefined) {
		Vue.prototype.OCA = oca
	}

	if (installCommonDirectives && !configuredConstructors.has(Vue)) {
		Vue.directive('click-outside', clickOutside)
		Vue.directive('focus', focus)
		configuredConstructors.add(Vue)
	}

	if (installErrorHandler) {
		Vue.config.errorHandler = (err) => {
			if (err.response && err.response.data.message) {
				const errorMessage = translate?.('deck', 'Something went wrong') ?? 'Something went wrong'
				showError(`${errorMessage}: ${err.response.data.status} ${err.response.data.message}`)
			}
			console.error(err)
		}
	}

	return Vue
}

export function mountVueRoot(Vue, options, target = null) {
	const root = new Vue(options)
	return target ? root.$mount(target) : root.$mount()
}