/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

export function appendMountTarget({ id = null, parent = document.getElementById('body-user') || document.body } = {}) {
	const container = document.createElement('div')
	if (id) {
		container.id = id
	}
	parent.append(container)
	return container
}

export function mountComponent(Vue, Component, {
	target,
	props = {},
	store,
	on = {},
} = {}) {
	const root = new Vue({
		store,
		render: (createElement) => createElement(Component, { props, on }),
	}).$mount(target)

	let destroyed = false

	return {
		element: root.$el,
		root,
		destroy({ removeElement = false } = {}) {
			if (destroyed) {
				return
			}

			destroyed = true
			if (removeElement && root.$el?.parentNode) {
				root.$el.parentNode.removeChild(root.$el)
			}
			root.$destroy()
		},
	}
}