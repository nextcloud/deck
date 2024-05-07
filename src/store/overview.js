/**
 * SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import Vue from 'vue'
import Vuex from 'vuex'
import { OverviewApi } from '../services/OverviewApi.js'
Vue.use(Vuex)

const apiClient = new OverviewApi()
export default {
	state: {
		assignedCards: [],
		loading: false,
	},
	getters: {
		assignedCardsDashboard: state => {
			return state.assignedCards
		},
	},
	mutations: {
		setAssignedCards(state, assignedCards) {
			state.assignedCards = assignedCards
		},
		setLoading(state, promise) {
			state.loading = promise
		},
	},
	actions: {
		async loadUpcoming({ state, commit }) {
			if (state.loading) {
				return state.loading
			}
			const promise = (async () => {
				commit('setCurrentBoard', null)
				const assignedCards = await apiClient.get('upcoming')
				const assignedCardsFlat = Object.values(assignedCards).flat()
				for (const i in assignedCardsFlat) {
					commit('addCard', assignedCardsFlat[i])
				}
				commit('setAssignedCards', assignedCards)
				commit('setLoading', false)
			})()
			commit('setLoading', promise)
			return promise
		},
	},
}
