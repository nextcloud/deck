/**
 * SPDX-FileCopyrightText: 2021 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
import { createApp, defineAsyncComponent, h } from 'vue'

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
		// app.config.globalProperties.t = t
		// app.config.globalProperties.n = n
		// app.config.globalProperties.OC = OC
		// app.config.globalProperties.OCA = OCA

		app.mount(container)
	})
}

export {
	buildSelector,
}
