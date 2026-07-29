/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { defineStore } from 'pinia'
import { StackApi } from '../services/StackApi.js'
import { CardApi } from '../services/CardApi.js'

const stackApi = new StackApi()
const cardApi = new CardApi()

export const useTrashbinStore = defineStore('trashbin', {
	state: () => ({
		deletedStacks: [],
		deletedCards: [],
	}),
	actions: {
		setDeletedStacks(delStacks) {
			this.deletedStacks = []
			if (delStacks.length > 0) {
				this.deletedStacks = delStacks
			}
		},
		moveStackToTrash(stack) {
			stack.deletedAt = Math.floor(Date.now() / 1000)
			this.deletedStacks.push(stack)
		},
		removeStackFromTrash(stack) {
			const existingIndex = this.deletedStacks.findIndex(_stack => _stack.id === stack.id)
			if (existingIndex !== -1) {
				this.deletedStacks.splice(existingIndex, 1)
			}
		},
		setDeletedCards(delCards) {
			this.deletedCards = []
			this.deletedCards = delCards
		},
		moveCardToTrash(card) {
			card.deletedAt = Math.floor(Date.now() / 1000)
			this.deletedCards.push(card)
		},
		removeCardFromTrash(card) {
			const existingIndex = this.deletedCards.findIndex(_card => _card.id === card.id)
			if (existingIndex !== -1) {
				this.deletedCards.splice(existingIndex, 1)
			}
		},
		fetchDeletedItems(boardId) {
			stackApi.deletedStacks(boardId).then((deletedStacks) => {
				this.setDeletedStacks(deletedStacks)
			})
			cardApi.deletedCards(boardId).then((deletedCards) => {
				this.setDeletedCards(deletedCards)
			})
		},
		stackUndoDelete(stack) {
			stackApi.updateStack(stack).then((restoredStack) => {
				this.$vuex.commit('addStack', restoredStack)
				this.removeStackFromTrash(restoredStack)
			})
		},
		cardUndoDelete(card) {
			cardApi.updateCard(card).then((restoredCard) => {
				this.removeCardFromTrash(restoredCard)
				this.$vuex.commit('addCard', restoredCard)
			})
		},
	},
})
