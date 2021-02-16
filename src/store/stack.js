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

import Vue from 'vue'
import { StackApi } from './../services/StackApi'
import applyOrderToArray from './../helpers/applyOrderToArray'

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
				Vue.set(state.stacks, existingIndex, Object.assign({}, existingStack, stack))
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
			for (const i in stacks) {
				const stack = stacks[i]
				for (const j in stack.cards) {
					commit('addCard', stack.cards[j])
				}
				delete stack.cards
				commit('addStack', stack)
			}
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
