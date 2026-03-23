/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { createRenderFunction, mountVueRoot } from './vue.js'

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
	router,
} = {}) {
	const mounted = mountVueRoot(Vue, {
		router,
		store,
		render: createRenderFunction(Vue, Component, { props, on }),
	}, target)

	let destroyed = false

	return {
		element: mounted.element,
		root: mounted.root,
		destroy({ removeElement = false } = {}) {
			if (destroyed) {
				return
			}

			destroyed = true
			if (removeElement && mounted.element?.parentNode) {
				mounted.element.parentNode.removeChild(mounted.element)
			}
			mounted.destroy()
		},
	}
}
