/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { defineStore } from 'pinia'
import { OverviewApi } from '../services/OverviewApi.js'
import { useCardStore } from './card.js'
import { useBoardStore } from './board.js'

const overviewApi = new OverviewApi()

export const useOverviewStore = defineStore('overview', {
	state: () => ({
		assignedCards: [],
		loading: false,
	}),
	actions: {
		async loadUpcoming() {
			if (this.loading) {
				return this.loading
			}
			const promise = (async () => {
				const cardStore = useCardStore()
				useBoardStore().setCurrentBoard(null)
				const assignedCards = await overviewApi.get('upcoming')
				const assignedCardsFlat = Object.values(assignedCards).flat()
				for (const i in assignedCardsFlat) {
					cardStore.addCardToStore(assignedCardsFlat[i])
				}
				this.assignedCards = assignedCards
				this.loading = false
			})()
			this.loading = promise
			return promise
		},
	},
})
