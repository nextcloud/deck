/*
 * @copyright Copyright (c) 2018 Julius Härtl <jus@bitgrid.net>
 *
 * @author Julius Härtl <jus@bitgrid.net>
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

import { CardApi } from './../services/CardApi'

const apiClient = new CardApi()

export default {
	state: {
		cards: []
	},
	getters: {
		cardsByStack: state => (id) => {
			return state.cards.filter((card) => card.stackId === id).sort((a, b) => a.order - b.order)
		},
		cardById: state => (id) => {
			return state.cards.find((card) => card.id === id)
		}
	},
	mutations: {
		addCard(state, card) {
			state.cards.push(card)
			/* let existingIndex = state.cards.findIndex(_card => _card.id === card.id)
			if (existingIndex !== -1) {
				let existingCard = state.cards.find(_card => _card.id === card.id)
				Vue.set(state.cards, existingIndex, Object.assign({}, existingCard, card))
			} else {
				state.cards.push(card)
			} */
		}
	},
	actions: {
		addCard({ commit }, card) {
			apiClient.addCard(card)
				.then((createdCard) => {
					commit('addCard', createdCard)
				})
		}
	}
}
