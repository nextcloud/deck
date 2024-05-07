/**
 * SPDX-FileCopyrightText: 2021 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
import Vue from 'vue'

const buildSelector = (selector, propsData = {}) => {
	return new Promise((resolve, reject) => {
		const container = document.createElement('div')
		document.getElementById('body-user').append(container)
		const ComponentVM = new Vue({
			render: (h) => h(selector, propsData),
		}).$mount(container)
		ComponentVM.$root.$on('close', () => {
			ComponentVM.$el.remove()
			ComponentVM.$destroy()
			reject(new Error('Selection canceled'))
		})
		ComponentVM.$root.$on('select', (id) => {
			ComponentVM.$el.remove()
			ComponentVM.$destroy()
			resolve(id)
		})
	})
}

export {
	buildSelector,
}
