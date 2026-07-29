/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { defineStore } from 'pinia'
import { OverviewApi } from '../services/OverviewApi.js'

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
				this.$vuex.commit('setCurrentBoard', null)
				const assignedCards = await overviewApi.get('upcoming')
				const assignedCardsFlat = Object.values(assignedCards).flat()
				for (const i in assignedCardsFlat) {
					this.$vuex.commit('addCard', assignedCardsFlat[i])
				}
				this.assignedCards = assignedCards
				this.loading = false
			})()
			this.loading = promise
			return promise
		},
	},
})
