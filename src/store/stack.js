/**
 * SPDX-FileCopyrightText: 2018 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { StackApi } from './../services/StackApi.js'
import applyOrderToArray from './../helpers/applyOrderToArray.js'

const apiClient = new StackApi()

export default {
	state: {
		stacks: [],
	},
	getters: {
		stacksByBoard: state => (id) => {
			return state.stacks.filter((stack) => stack.boardId === id).sort((a, b) => a.order - b.order)
		},
		stackById: state => (id) => {
			return state.stacks.find((stack) => stack.id === id)
		},
	},
	mutations: {
		addStack(state, stack) {
			const existingIndex = state.stacks.findIndex(_stack => _stack.id === stack.id)
			if (existingIndex !== -1) {
				const existingStack = state.stacks.find(_stack => _stack.id === stack.id)
				state.stacks[existingIndex] = { ...existingStack, ...stack }
			} else {
				state.stacks.push(stack)
			}
		},
		orderStack(state, { stack, removedIndex, addedIndex }) {
			const currentOrder = state.stacks.filter((_stack) => _stack.boardId === stack.boardId).sort((a, b) => a.order - b.order)
			const newOrder = applyOrderToArray(currentOrder, removedIndex, addedIndex)
			for (let i = 0; i < newOrder.length; i++) {
				newOrder[i].order = parseInt(i)
			}
		},
		deleteStack(state, stack) {
			const existingIndex = state.stacks.findIndex(_stack => _stack.id === stack.id)
			if (existingIndex !== -1) {
				state.stacks.splice(existingIndex, 1)
			}
		},
		updateStack(state, stack) {
			const existingIndex = state.stacks.findIndex(_stack => _stack.id === stack.id)
			if (existingIndex !== -1) {
				state.stacks[existingIndex].title = stack.title
			}
		},
	},
	actions: {
		orderStack({ commit }, { stack, removedIndex, addedIndex }) {
			commit('orderStack', { stack, removedIndex, addedIndex })
			apiClient.reorderStack(stack.id, addedIndex)
				.catch((err) => {
					OC.Notification.showTemporary('Failed to change order')
					console.error(err.response.data.message)
					commit('orderStack', { stack, addedIndex, removedIndex })
				})
		},
		async loadStacks({ commit }, boardId) {
			let call = 'loadStacks'
			if (this.state.showArchived === true) {
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
				commit('addStack', stack)
			}
			commit('setCards', cards)
		},
		async loadArchivedStacks({ commit, getters }, boardId) {
			const archivedStacks = await apiClient.loadArchivedStacks(boardId)
			const cards = []
			for (const i in archivedStacks) {
				const stack = archivedStacks[i]
				for (const j in stack.cards) {
					cards.push(stack.cards[j])
				}
				delete stack.cards
				if (!getters.stackById(stack.id)) {
					commit('addStack', stack)
				}
			}
			commit('setCards', cards)
		},
		createStack({ commit }, stack) {
			stack.boardId = this.state.currentBoard.id
			apiClient.createStack(stack)
				.then((createdStack) => {
					commit('addStack', createdStack)
				})
		},
		deleteStack({ commit }, stack) {
			apiClient.deleteStack(stack.id)
				.then((stack) => {
					commit('deleteStack', stack)
					commit('moveStackToTrash', stack)
				})
		},
		updateStack({ commit }, stack) {
			apiClient.updateStack(stack)
				.then((stack) => {
					commit('updateStack', stack)
				})
		},
	},
}
