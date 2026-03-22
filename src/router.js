/**
 * SPDX-FileCopyrightText: 2018 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import Vue from 'vue'
import Router from 'vue-router'
import { getDeckRouterOptions, registerDeckRouterGuards } from './router/config.js'

Vue.use(Router)

export function createDeckRouter() {
	const router = new Router(getDeckRouterOptions())
	registerDeckRouterGuards(router)
	return router
}

const router = createDeckRouter()

export default router
