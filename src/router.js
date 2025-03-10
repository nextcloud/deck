/**
 * SPDX-FileCopyrightText: 2018 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import Router, { createRouter, createWebHistory } from 'vue-router'
import Vue from 'vue'
import { generateUrl, getRootUrl } from '@nextcloud/router'
import { BOARD_FILTERS } from './store/main.js'
import Boards from './components/boards/Boards.vue'
import Board from './components/board/Board.vue'
import Sidebar from './components/Sidebar.vue'
import BoardSidebar from './components/board/BoardSidebar.vue'
import CardSidebar from './components/card/CardSidebar.vue'
import Overview from './components/overview/Overview.vue'

Vue.use(Router)

// We apply a dynamic base URL depending on the URL used in the browser
const baseUrl = generateUrl('/apps/deck/')
const webRootWithIndexPHP = getRootUrl() + '/index.php'
const doesURLContainIndexPHP = window.location.pathname.startsWith(webRootWithIndexPHP)
const currentBaseUrl = doesURLContainIndexPHP ? baseUrl : baseUrl.replace('/index.php/', '/')

const router = createRouter({
	history: createWebHistory(generateUrl('/apps/deck/')),
	base: currentBaseUrl,
	linkActiveClass: 'active',
	routes: [
		{
			path: '/',
			name: 'main',
			component: Overview,
		},
		{
			path: '/overview/:filter',
			name: 'overview',
			components: {
				default: Overview,
			},
			props: {
				default: (route) => {
					return {
						filter: route.params.filter,
					}
				},
			},
		},
		{
			path: '/board',
			name: 'boards',
			component: Boards,
			props: {
				navFilter: BOARD_FILTERS.ALL,
			},
		},
		{
			path: '/board/archived',
			name: 'boards.archived',
			component: Boards,
			props: {
				navFilter: BOARD_FILTERS.ARCHIVED,
			},
		},
		{
			path: '/board/shared',
			name: 'boards.shared',
			component: Boards,
			props: {
				navFilter: BOARD_FILTERS.SHARED,
			},
		},
		{
			path: '/board/:id',
			name: 'board',
			components: {
				default: Board,
				sidebar: Sidebar,
			},
			props: {
				default: (route) => {
					return {
						id: parseInt(route.params.id, 10),
					}
				},
			},
			children: [
				{
					path: 'details',
					name: 'board.details',
					components: {
						default: Boards,
						sidebar: BoardSidebar,
					},
					props: {
						default: (route) => {
							return {
								id: parseInt(route.params.id, 10),
							}
						},
						sidebar: (route) => {
							return {
								id: parseInt(route.params.id, 10),
							}
						},
					},
				},
				{
					path: 'card/:cardId/:tabId?/:tabQuery?',
					name: 'card',
					components: {
						sidebar: CardSidebar,
					},
					props: {
						default: (route) => {
							return {
								cardId: parseInt(route.params.cardId, 10),
							}
						},
						sidebar: (route) => {
							return {
								id: parseInt(route.params.cardId, 10),
								tabId: route.params.tabId,
								tabQuery: route.params.tabQuery,
							}
						},
					},
				},
			],
		},
		// redirects to keep compatibility to 1.0.0 routes
		{
			path: '/boards/:id',
			redirect: '/board/:id',
		},
		{
			path: '/boards/:id/cards/:cardId',
			redirect: '/board/:id/card/:cardId',
		},
		{
			path: '/!/board/:id',
			redirect: '/board/:id',
		},
		{
			path: '/!/board/:id/card/:cardId',
			redirect: '/board/:id/card/:cardId',
		},
	],
})

router.beforeEach((to, from, next) => {
	// Redirect if fullPath begins with a hash (ignore hashes later in path)
	if (to.hash.substring(0, 2) === '#/') {
		const path = to.fullPath.replace('/#/', '/').trimEnd('/')
		next(path)
		return
	}
	next()
})

export default router
