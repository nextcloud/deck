/*
 * @copyright Copyright (c) 2020 Jakob Röhrl <jakob.roehrl@web.de>
 *
 * @author Jakob Röhrl <jakob.roehrl@web.de>
 *
 * @license GNU AGPL version 3 or any later version
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 *
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
