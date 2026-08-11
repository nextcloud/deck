/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import Vue from 'vue'
import { defineStore } from 'pinia'
import { StackApi } from '../services/StackApi.js'
import applyOrderToArray from '../helpers/applyOrderToArray.js'
import { useTrashbinStore } from './trashbin.js'
import { useCardStore } from './card.js'
import { useBoardStore } from './board.js'

const apiClient = new StackApi()

export const useStackStore = defineStore('stack', {
	state: () => ({
		stacks: [],
	}),
	getters: {
		stacksByBoard: (state) => (id) => {
			return state.stacks.filter((stack) => stack.boardId === id).sort((a, b) => a.order - b.order)
		},
		stackById: (state) => (id) => {
			return state.stacks.find((stack) => stack.id === id)
		},
	},
	actions: {
		addStack(stack) {
			const existingIndex = this.stacks.findIndex(_stack => _stack.id === stack.id)
			if (existingIndex !== -1) {
				Vue.set(this.stacks, existingIndex, Object.assign({}, this.stacks[existingIndex], stack))
			} else {
				this.stacks.push(stack)
			}
		},
		orderStack({ stack, removedIndex, addedIndex }) {
			const currentOrder = this.stacks.filter((_stack) => _stack.boardId === stack.boardId).sort((a, b) => a.order - b.order)
			const newOrder = applyOrderToArray(currentOrder, removedIndex, addedIndex)
			for (let i = 0; i < newOrder.length; i++) {
				newOrder[i].order = parseInt(i)
			}
			apiClient.reorderStack(stack.id, addedIndex, stack.boardId)
				.catch((err) => {
					OC.Notification.showTemporary('Failed to change order')
					console.error(err.response.data.message)

					// restore old order
					for (let i = 0; i < currentOrder.length; i++) {
						currentOrder[i].order = parseInt(i)
					}
				})
		},
		async loadStacks(boardId) {
			const cardStore = useCardStore()
			let call = 'loadStacks'
			if (useBoardStore().showArchived === true) {
				call = 'loadArchivedStacks'
			}
			const stacks = await apiClient[call](boardId)
			const cards = []
			for (const i in stacks) {
				const stack = stacks[i]
				for (const j in stack.cards) {
					cards.push(stack.cards[j])
				}
				delete stack.cards
				this.addStack(stack)
			}
			cardStore.setCards(cards)
		},
		async loadArchivedStacks(boardId) {
			const cardStore = useCardStore()
			const archivedStacks = await apiClient.loadArchivedStacks(boardId)
			const cards = []
			for (const i in archivedStacks) {
				const stack = archivedStacks[i]
				for (const j in stack.cards) {
					cards.push(stack.cards[j])
				}
				delete stack.cards
				if (!this.stackById(stack.id)) {
					this.addStack(stack)
				}
			}
			cardStore.setCards(cards)
		},
		createStack(stack) {
			stack.boardId = useBoardStore().currentBoard.id
			apiClient.createStack(stack)
				.then((createdStack) => {
					this.addStack(createdStack)
				})
		},
		deleteStack(stack) {
			apiClient.deleteStack(stack.id, stack.boardId)
				.then((stack) => {
					const existingIndex = this.stacks.findIndex(_stack => _stack.id === stack.id)
					if (existingIndex !== -1) {
						this.stacks.splice(existingIndex, 1)
					}
					useTrashbinStore().moveStackToTrash(stack)
				})
		},
		updateStack(stack) {
			apiClient.updateStack(stack)
				.then((stack) => {
					const existingIndex = this.stacks.findIndex(_stack => _stack.id === stack.id)
					if (existingIndex !== -1) {
						this.stacks[existingIndex].title = stack.title
					}
				})
		},
		async setDoneStack({ stackId, boardId, isDone }) {
			const cardStore = useCardStore()
			await apiClient.setDoneStack(stackId, boardId, isDone)
			// Mirror the backend bulk-clear: clear the flag on any other stack in this board
			if (isDone) {
				this.stacks
					.filter((s) => s.id !== stackId && s.boardId === boardId && s.isDoneColumn)
					.forEach((s) => this.addStack({ ...s, isDoneColumn: false }))
				// Mirror the backend bulk-done: mark all undone cards in this stack as done
				const now = new Date().toISOString()
				cardStore.cards
					.filter((c) => c.stackId === stackId && c.done == null)
					.forEach((c) =>
						cardStore.updateCardProperty({ property: 'done', card: { ...c, done: now } }),
					)
			}
			const stack = this.stacks.find((s) => s.id === stackId)
			if (stack) {
				this.addStack({ ...stack, isDoneColumn: isDone })
			}
		},
	},
})
