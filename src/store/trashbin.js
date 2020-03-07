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

import { StackApi } from '../services/StackApi'
import { CardApi } from '../services/CardApi'

const stackApi = new StackApi()
const cardApi = new CardApi()

export default {
	state: {
		deletedStacks: [],
		deletedCards: [],
	},
	mutations: {
		setDeletedStacks(state, delStacks) {
			state.deletedStacks = []
			if (delStacks.length > 0) {
				state.deletedStacks = delStacks
			}
		},

		moveStackToTrash(state, stack) {
			stack.deletedAt = Math.floor(Date.now() / 1000)
			state.deletedStacks.push(stack)
		},

		removeStackFromTrash(state, stack) {
			const existingIndex = state.deletedStacks.findIndex(_stack => _stack.id === stack.id)
			if (existingIndex !== -1) {
				state.deletedStacks.splice(existingIndex, 1)
			}
		},

		setDeletedCards(state, delCards) {
			state.deletedCards = []
			state.deletedCards = delCards
		},

		moveCardToTrash(state, card) {
			card.deletedAt = Math.floor(Date.now() / 1000)
			state.deletedCards.push(card)
		},

		removeCardFromTrash(state, card) {
			const existingIndex = state.deletedCards.findIndex(_card => _card.id === card.id)
			if (existingIndex !== -1) {
				state.deletedCards.splice(existingIndex, 1)
			}
		},
	},
	actions: {
		fetchDeletedItems({ commit }, boardId) {
			stackApi.deletedStacks(boardId)
				.then((deletedStacks) => {
					commit('setDeletedStacks', deletedStacks)
				})
			cardApi.deletedCards(boardId)
				.then((deletedCards) => {
					commit('setDeletedCards', deletedCards)
				})
		},
		stackUndoDelete({ commit }, stack) {
			stackApi.updateStack(stack)
				.then((stack) => {
					commit('addStack', stack)
					commit('removeStackFromTrash', stack)
				})
		},
		cardUndoDelete({ commit }, card) {
			cardApi.updateCard(card)
				.then((card) => {
					commit('removeCardFromTrash', card)
					commit('addCard', card)
				})
		},
	},
}
