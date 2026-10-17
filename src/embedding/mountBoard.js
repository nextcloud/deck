/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import axios from '@nextcloud/axios'
import { translate, translatePlural } from '@nextcloud/l10n'
import { generateOcsUrl } from '@nextcloud/router'
import { createPinia, disposePinia } from 'pinia'
import { createApp } from 'vue'
import EmbeddedBoard from './EmbeddedBoard.vue'
import logger from '../logger.js'
import { BoardApi } from '../services/BoardApi.js'
import { createSession } from '../sessions.js'
import { useBoardStore } from '../stores/board.js'
import { useSettingsStore } from '../stores/settings.js'
import { useStackStore } from '../stores/stack.js'
import { registerBoardUpdates } from './push.js'
import { createBoardRouter } from './router.js'

export async function mountBoard(element, { boardId, cardId, signal, onReady, onError, onNavigate }) {
	const pinia = createPinia()
	const router = createBoardRouter(boardId, onNavigate)
	const app = createApp(EmbeddedBoard, { boardId, onReady, onError })
	app.use(pinia)
	app.config.globalProperties.t = translate
	app.config.globalProperties.n = translatePlural
	app.config.globalProperties.OC = window.OC
	app.config.globalProperties.OCA = window.OCA
	app.config.errorHandler = (error) => {
		logger.error('Embedded Deck component failed', { error })
		onError(error)
	}
	app.directive('focus', { mounted: (el) => el.focus() })
	app.provide('boardApi', new BoardApi())

	const board = useBoardStore(pinia)
	const settings = useSettingsStore(pinia)
	const stacks = useStackStore(pinia)
	board.setBoards([])
	settings.setFullApp(false)
	let mounted = false
	let disposed = false
	let updates
	let stopNavigation
	const unmount = () => {
		if (disposed) {
			return
		}
		disposed = true
		stopNavigation?.()
		updates?.dispose()
		if (mounted) {
			app.unmount()
		}
		disposePinia(pinia)
		signal.removeEventListener('abort', unmount)
	}
	signal.addEventListener('abort', unmount, { once: true })

	const cardRoute = (id) => ({
		name: id ? 'card' : 'board',
		params: { id: boardId, ...(id ? { cardId: id } : {}) },
	})
	try {
		if (signal.aborted) {
			unmount()
			return null
		}
		const response = await axios.get(generateOcsUrl('apps/deck/api/v1.0/config'), { signal })
		if (signal.aborted) {
			return null
		}
		settings.config = response.data.ocs.data
		await board.loadBoards()
		if (signal.aborted) {
			return null
		}
		await router.replace(cardRoute(cardId))
		await router.isReady()
		if (signal.aborted) {
			return null
		}
		app.use(router)

		const report = (error) => {
			if (!disposed) {
				logger.error('Failed to update embedded Deck board', { error })
				onError(error)
			}
		}
		updates = registerBoardUpdates({
			boardId,
			refreshBoard: () => {
				if (board.currentBoard) {
					board.refreshBoard(boardId).catch(report)
				}
			},
			refreshCards: () => {
				if (board.currentBoard) {
					stacks.loadStacks(boardId).catch(report)
				}
			},
		})
		app.provide('deckEmbedding', {
			pushEnabled: updates.pushEnabled,
			createSession: (id) => createSession(id, {
				pushEnabled: updates.pushEnabled,
				boardStore: board,
				globalHeader: false,
				onError: report,
			}),
		})
		stopNavigation = router.afterEach((to, from, failure) => {
			if (!failure && to.fullPath !== from.fullPath) {
				onNavigate({
					boardId,
					cardId: to.params.cardId ? Number(to.params.cardId) : null,
					tabId: to.params.tabId ?? null,
				})
			}
		})
		mounted = true
		app.mount(element)
		return {
			unmount,
			setCard: (id) => {
				const currentId = router.currentRoute.value.params.cardId
				if ((currentId ? Number(currentId) : null) === id) {
					return Promise.resolve()
				}
				return router.replace(cardRoute(id))
			},
		}
	} catch (error) {
		unmount()
		throw error
	}
}
