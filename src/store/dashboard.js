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
	},
	getters: {
		assignedCardsDashboard: state => {
			return Object.values(state.assignedCards).flat()
		},
	},
	mutations: {
		setAssignedCards(state, assignedCards) {
			state.assignedCards = assignedCards
		},
	},
	actions: {
		async loadUpcoming({ commit }) {
			const upcommingCards = await apiClient.get('upcoming')
			commit('setAssignedCards', upcommingCards)
		},
	},
}
