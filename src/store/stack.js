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
import { BoardApi } from './../services/BoardApi'
import { StackApi } from './../services/StackApi'
import applyOrderToArray from './../helpers/applyOrderToArray'
import { emit } from '@nextcloud/event-bus'

const boardApiClient = new BoardApi()
const apiClient = new StackApi()

export default {
	state: {
		stacks: [],
		remoteUpdate: null,
	},
	getters: {
		stacksByBoard: state => (id) => {
			return state.stacks.filter((stack) => stack.boardId === id).sort((a, b) => a.order - b.order)
		},
	},
	mutations: {
		clearStacks(state) {
			state.stacks = []
		},
		updateRemote(state, response) {
			Vue.set(state, 'remoteUpdate', response)
		},
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
					emit('deck:stack:modified', { ...stack, lastModified: Date.now() / 1000 })
				})
		},
		async loadStacks({ commit }, boardId) {
			commit('clearCards')
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
				emit('deck:stack:modified', { ...stack, lastModified: Date.now() / 1000 })
			}
		},
		async poll({ commit, rootState, state }, boardId) {
			if (!rootState.currentBoard) {
				return
			}
			// TODO: set If-Modified-Since header
			const board = await boardApiClient.loadById(rootState.currentBoard.id)

			console.debug('[deck] poll: remote(' + board.lastModified + ') local(' + rootState.currentBoard.lastModified + ') update(' + state.remoteUpdate?.lastModified + ')')
			if (rootState.currentBoard.lastModified >= board.lastModified || state.remoteUpdate?.lastModified === board.lastModified) {
				console.debug('[deck] poll: no new data for board ' + board.title)
				return
			}

			let call = 'loadStacks'
			if (this.state.showArchived === true) {
				call = 'loadArchivedStacks'
			}
			const stacks = await apiClient[call](boardId)
			board.stacks = stacks
			commit('updateRemote', board)
			console.debug('[deck] poll: applied new data for board ' + board.title)

		},
		async pollApply({ commit, state }, boardId) {
			commit('clearCards')
			commit('clearStacks')
			// TODO: trigger board updated at on every operation
			// event bus deck:board:modified board
			// event bus deck:card:modified card
			// event bus deck:stack:modified stack
			for (const i in state.remoteUpdate.stacks) {
				const stack = state.remoteUpdate.stacks[i]
				for (const j in stack.cards) {
					commit('addCard', stack.cards[j])
				}
				delete stack.cards
				commit('addStack', stack)
			}
			delete state.remoteUpdate.stacks
			commit('setCurrentBoard', state.remoteUpdate)
			commit('updateRemote', null)
		},
		createStack({ commit }, stack) {
			stack.boardId = this.state.currentBoard.id
			apiClient.createStack(stack)
				.then((createdStack) => {
					commit('addStack', createdStack)
					emit('deck:stack:modified', { ...createdStack, lastModified: Date.now() / 1000 })
				})
		},
		deleteStack({ commit }, stack) {
			apiClient.deleteStack(stack.id)
				.then((stack) => {
					commit('deleteStack', stack)
					commit('moveStackToTrash', stack)
					emit('deck:stack:modified', { ...stack, lastModified: Date.now() / 1000 })
				})
		},
		updateStack({ commit }, stack) {
			apiClient.updateStack(stack)
				.then((stack) => {
					commit('updateStack', stack)
					emit('deck:stack:modified', { ...stack, lastModified: Date.now() / 1000 })
				})
		},
	},
}
