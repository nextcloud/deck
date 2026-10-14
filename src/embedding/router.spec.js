/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { expect, jest, test } from '@jest/globals'
import { createBoardRouter } from './router.js'

jest.mock('@vue/devtools-api', () => ({ setupDevtoolsPlugin: jest.fn() }))

test('memory router resolves board and card routes', async () => {
	const navigate = jest.fn()
	const router = createBoardRouter(42, navigate)
	await router.push({ name: 'board', params: { id: 42 } })
	await router.push({ name: 'card', params: { id: 42, cardId: 7, tabId: 'comments' } })
	expect(router.currentRoute.value.params).toEqual({ id: '42', cardId: '7', tabId: 'comments' })
	expect(navigate).not.toHaveBeenCalled()
})

test('cross-board navigation is delegated to the host, not followed', async () => {
	const navigate = jest.fn()
	const router = createBoardRouter(42, navigate)
	await router.push({ name: 'board', params: { id: 42 } })
	await router.push({ name: 'card', params: { id: 43, cardId: 8 } })
	expect(router.currentRoute.value.name).toBe('board')
	expect(router.currentRoute.value.params.id).toBe('42')
	expect(navigate).toHaveBeenCalledWith({ boardId: 43, cardId: 8, tabId: null })
})

test('routers are independent', async () => {
	const first = createBoardRouter(42, jest.fn())
	const second = createBoardRouter(43, jest.fn())
	await first.push({ name: 'board', params: { id: 42 } })
	await second.push({ name: 'card', params: { id: 43, cardId: 8 } })
	expect(first.currentRoute.value.name).toBe('board')
	expect(second.currentRoute.value.name).toBe('card')
})
