/**
 * SPDX-FileCopyrightText: 2018 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import Vue from 'vue'
import * as VueRouter from 'vue-router'
import { getDeckRouterOptions, registerDeckRouterGuards } from './router/config.js'
import { createDeckRouterInstance, installDeckRouter } from './router/runtime.js'

installDeckRouter(Vue, VueRouter)

export function createDeckRouter() {
	const router = createDeckRouterInstance(VueRouter, getDeckRouterOptions())
	registerDeckRouterGuards(router)
	return router
}

const router = createDeckRouter()

export default router
