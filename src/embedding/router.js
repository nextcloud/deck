/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { createMemoryHistory, createRouter } from 'vue-router'

const EmptyView = { render: () => null }

export function createBoardRouter(boardId, onNavigate) {
	const router = createRouter({
		history: createMemoryHistory(),
		routes: [
			{ path: '/', redirect: { name: 'board', params: { id: boardId } } },
			{ path: '/board/:id', name: 'board', component: EmptyView },
			{ path: '/board/:id/card/:cardId/:tabId?/:tabQuery?', name: 'card', component: EmptyView },
		],
	})
	router.beforeEach((to) => {
		if (Number(to.params.id) !== boardId) {
			onNavigate({
				boardId: Number(to.params.id),
				cardId: to.params.cardId ? Number(to.params.cardId) : null,
				tabId: to.params.tabId ?? null,
			})
			return false
		}
	})
	return router
}
