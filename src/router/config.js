/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { generateUrl, getRootUrl } from '@nextcloud/router'
import { BOARD_FILTERS } from '../store/main.js'
import Boards from '../components/boards/Boards.vue'
import Board from '../components/board/Board.vue'
import Sidebar from '../components/Sidebar.vue'
import BoardSidebar from '../components/board/BoardSidebar.vue'
import CardSidebar from '../components/card/CardSidebar.vue'
import Overview from '../components/overview/Overview.vue'

function toInteger(value) {
	return parseInt(value, 10)
}

function boardIdProps(route) {
	return {
		id: toInteger(route.params.id),
	}
}

function normalizeRedirectPath(path) {
	return path.replace('/#/', '/').trimEnd('/')
}

export function getDeckRouterBase() {
	const baseUrl = generateUrl('/apps/deck/')
	const webRootWithIndexPHP = getRootUrl() + '/index.php'
	const doesURLContainIndexPHP = window.location.pathname.startsWith(webRootWithIndexPHP)

	return doesURLContainIndexPHP ? baseUrl : baseUrl.replace('/index.php/', '/')
}

export function createDeckRoutes() {
	return [
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
				default: (route) => ({
					filter: route.params.filter,
				}),
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
				default: boardIdProps,
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
						default: boardIdProps,
						sidebar: boardIdProps,
					},
				},
				{
					path: 'card/:cardId/:tabId?/:tabQuery?',
					name: 'card',
					components: {
						sidebar: CardSidebar,
					},
					props: {
						default: (route) => ({
							cardId: toInteger(route.params.cardId),
						}),
						sidebar: (route) => ({
							id: toInteger(route.params.cardId),
							tabId: route.params.tabId,
							tabQuery: route.params.tabQuery,
						}),
					},
				},
			],
		},
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
	]
}

export function getDeckRouterOptions() {
	return {
		mode: 'history',
		base: getDeckRouterBase(),
		linkActiveClass: 'active',
		routes: createDeckRoutes(),
	}
}

export function getDeckRouteRedirect(to) {
	if (to.hash.substring(0, 2) === '#/') {
		return normalizeRedirectPath(to.fullPath)
	}

	if (to.name === 'main') {
		const defaultBoardId = localStorage.getItem('deck.defaultBoardId')
		if (defaultBoardId) {
			return { name: 'board', params: { id: toInteger(defaultBoardId) } }
		}
	}

	return null
}

export function registerDeckRouterGuards(router) {
	router.beforeEach((to, from, next) => {
		const redirect = getDeckRouteRedirect(to)
		if (typeof next === 'function') {
			if (redirect) {
				next(redirect)
				return
			}

			next()
			return
		}

		return redirect || true
	})
}