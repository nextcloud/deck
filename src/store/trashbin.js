/**
 * SPDX-FileCopyrightText: 2018 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { StackApi } from '../services/StackApi.js'
import { CardApi } from '../services/CardApi.js'

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
