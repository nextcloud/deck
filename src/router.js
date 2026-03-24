/**
 * SPDX-FileCopyrightText: 2018 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { createRouter, createWebHistory } from 'vue-router'
import { createDeckRoutes, getDeckRouterBase, registerDeckRouterGuards } from './router/config.js'

export function createDeckRouter() {
	const router = createRouter({
		history: createWebHistory(getDeckRouterBase()),
		routes: createDeckRoutes(),
		linkActiveClass: 'active',
	})
	registerDeckRouterGuards(router)
	return router
}

const router = createDeckRouter()

export default router
