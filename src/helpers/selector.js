/**
 * SPDX-FileCopyrightText: 2021 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
import { createApp, defineAsyncComponent, h } from 'vue'
import { translate, translatePlural } from '@nextcloud/l10n'

const buildSelector = (selector, propsData = {}) => {
	return new Promise((resolve, reject) => {
		const container = document.createElement('div')
		document.getElementById('body-user').append(container)

		const component = typeof selector === 'function' ? defineAsyncComponent(selector) : selector
		const selectorProps = propsData?.props ?? propsData
		let settled = false

		const cleanup = () => {
			if (app) {
				app.unmount()
			}
			container.remove()
		}

		const onClose = () => {
			if (settled) {
				return
			}
			settled = true
			cleanup()
			reject(new Error('Selection canceled'))
		}

		const onSelect = (id) => {
			if (settled) {
				return
			}
			settled = true
			cleanup()
			resolve(id)
		}

		const app = createApp({
			render() {
				return h(component, {
					...selectorProps,
					onClose,
					onSelect,
				})
			},
		})
		// Unlike Vue 2, where these were set once on Vue.prototype, every Vue 3
		// app needs its own global properties and directives - the selectors are
		// mounted as standalone apps and would otherwise fail to render.
		app.config.globalProperties.t = translate
		app.config.globalProperties.n = translatePlural
		app.config.globalProperties.OC = window.OC
		app.config.globalProperties.OCA = window.OCA

		app.directive('focus', {
			mounted(el) {
				el.focus()
			},
		})

		app.mount(container)
	})
}

export {
	buildSelector,
}
