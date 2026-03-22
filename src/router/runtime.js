/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

function getVueModule(Vue) {
	return Vue?.default || Vue
}

function getRouterModule(VueRouter) {
	return VueRouter?.default || VueRouter
}

export function isModernRouterApi(VueRouter) {
	return typeof VueRouter?.createRouter === 'function'
		&& typeof VueRouter?.createWebHistory === 'function'
}

export function installDeckRouter(Vue, VueRouter) {
	if (!isModernRouterApi(VueRouter)) {
		getVueModule(Vue).use(getRouterModule(VueRouter))
	}
}

export function createDeckRouterInstance(VueRouter, options) {
	if (isModernRouterApi(VueRouter)) {
		return VueRouter.createRouter({
			history: VueRouter.createWebHistory(options.base),
			routes: options.routes,
			linkActiveClass: options.linkActiveClass,
		})
	}

	const Router = getRouterModule(VueRouter)
	return new Router(options)
}