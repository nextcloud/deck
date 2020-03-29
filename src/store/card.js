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
import Vue from 'vue'

const apiClient = new CardApi()

export default {
	state: {
		cards: [],
	},
	getters: {
		cardsByStack: (state, getters, rootState) => (id) => {
			return state.cards.filter((card) => {
				const { tags, users, due } = rootState.filter
				let allTagsMatch = true
				let allUsersMatch = true

				if (tags.length > 0) {
					tags.forEach((tag) => {
						if (card.labels.findIndex((l) => l.id === tag) === -1) {
							allTagsMatch = false
						}
					})
					if (!allTagsMatch) {
						return false
					}
				}

				if (users.length > 0) {
					users.forEach((user) => {
						if (card.assignedUsers.findIndex((u) => u.participant.uid === user) === -1) {
							allUsersMatch = false
						}
					})
					if (!allUsersMatch) {
						return false
					}
				}

				if (due !== '') {
					const datediffHour = ((new Date(card.duedate) - new Date()) / 3600000)
					switch (due) {
					case 'noDue':
						return (card.duedate === null)
					case 'overdue':
						return (card.overdue === 3)
					case 'dueToday':
						return (card.overdue >= 2)
					case 'dueWeek':
						return (datediffHour <= 168 && card.duedate !== null)
					case 'dueMonth':
						return (datediffHour <= 5040 && card.duedate !== null)
					}
				}

				return true
			})
				.filter((card) => card.stackId === id && (getters.getSearchQuery === ''
					|| (card.title.toLowerCase().includes(getters.getSearchQuery.toLowerCase())
					|| card.description.toLowerCase().includes(getters.getSearchQuery.toLowerCase()))
						.sort((a, b) => a.order - b.order)))
		},
		cardById: state => (id) => {
			return state.cards.find((card) => card.id === id)
		},
	},
	mutations: {
		clearCards(state) {
			state.cards = []
		},
		addCard(state, card) {
			const existingIndex = state.cards.findIndex(_card => _card.id === card.id)
			if (existingIndex !== -1) {
				const existingCard = state.cards.find(_card => _card.id === card.id)
				Vue.set(state.cards, existingIndex, Object.assign({}, existingCard, card))
			} else {
				state.cards.push(card)
			}
		},
		deleteCard(state, card) {
			const existingIndex = state.cards.findIndex(_card => _card.id === card.id)
			if (existingIndex !== -1) {
				state.cards.splice(existingIndex, 1)
			}
		},
		updateCard(state, card) {
			const existingIndex = state.cards.findIndex(_card => _card.id === card.id)
			if (existingIndex !== -1) {
				Vue.set(state.cards, existingIndex, Object.assign({}, state.cards[existingIndex], card))
			}
		},
		updateCardsReorder(state, cards) {
			for (const newCard of cards) {
				const existingIndex = state.cards.findIndex(_card => _card.id === newCard.id)
				if (existingIndex !== -1) {
					const newCardObject = Object.assign({}, state.cards[existingIndex], { id: newCard.id, order: newCard.order, stackId: newCard.stackId })
					Vue.set(state.cards, existingIndex, newCardObject)
				}
			}
		},
		assignCardToUser(state, user) {
			const existingIndex = state.cards.findIndex(_card => _card.id === user.cardId)
			if (existingIndex !== -1) {
				state.cards[existingIndex].assignedUsers.push(user)
			}
		},
		removeUserFromCard(state, user) {
			const existingIndex = state.cards.findIndex(_card => _card.id === user.cardId)
			if (existingIndex !== -1) {
				const foundIndex = state.cards[existingIndex].assignedUsers.findIndex(_user => _user.id === user.id)
				if (foundIndex !== -1) {
					state.cards[existingIndex].assignedUsers.splice(foundIndex, 1)
				}
			}
		},
		updateCardProperty(state, { card, property }) {
			const existingIndex = state.cards.findIndex(_card => _card.id === card.id)
			if (existingIndex !== -1) {
				Vue.set(state.cards[existingIndex], property, card[property])
			}
		},
		cardIncreaseAttachmentCount(state, cardId) {
			const existingIndex = state.cards.findIndex(_card => _card.id === cardId)
			if (existingIndex !== -1) {
				Vue.set(state.cards[existingIndex], 'attachmentCount', state.cards[existingIndex].attachmentCount + 1)
			}
		},
		cardDecreaseAttachmentCount(state, cardId) {
			const existingIndex = state.cards.findIndex(_card => _card.id === cardId)
			if (existingIndex !== -1) {
				Vue.set(state.cards[existingIndex], 'attachmentCount', state.cards[existingIndex].attachmentCount - 1)
			}
		},
	},
	actions: {
		async addCard({ commit }, card) {
			const createdCard = await apiClient.addCard(card)
			commit('addCard', createdCard)
		},
		async updateCardTitle({ commit }, card) {
			const updatedCard = await apiClient.updateCard(card)
			commit('updateCardProperty', { property: 'title', card: updatedCard })
		},
		async moveCard({ commit }, card) {
			const updatedCard = await apiClient.updateCard(card)
			commit('deleteCard', updatedCard)
		},
		async reorderCard({ commit, getters }, card) {
			let i = 0
			for (const c of getters.cardsByStack(card.stackId)) {
				if (c.id === card.id) {
					await commit('updateCardsReorder', [card])
				}
				if (i === card.order) {
					i++
				}
				if (c.id !== card.id) {
					await commit('updateCardsReorder', [{ ...c, order: i++ }])
				}
			}
			await commit('updateCardsReorder', [card])

			const cards = await apiClient.reorderCard(card)
			await commit('updateCardsReorder', Object.values(cards))
		},
		async deleteCard({ commit }, card) {
			await apiClient.deleteCard(card.id)
			commit('deleteCard', card)
			commit('moveCardToTrash', card)
		},
		async archiveUnarchiveCard({ commit }, card) {
			let call = 'archiveCard'
			if (card.archived === false) {
				call = 'unArchiveCard'
			}

			const updatedCard = await apiClient[call](card)
			commit('deleteCard', updatedCard)
		},
		async assignCardToUser({ commit }, card) {
			const user = await apiClient.assignUser(card)
			commit('assignCardToUser', user)
		},
		async removeUserFromCard({ commit }, card) {
			const user = await apiClient.removeUser(card)
			commit('removeUserFromCard', user)
		},
		async addLabel({ commit }, data) {
			await apiClient.assignLabelToCard(data)
			commit('updateCardProperty', { property: 'labels', card: data.card })
		},
		async removeLabel({ commit }, data) {
			await apiClient.removeLabelFromCard(data)
			commit('updateCardProperty', { property: 'labels', card: data.card })
		},
		async updateCardDesc({ commit }, card) {
			const updatedCard = await apiClient.updateCard(card)
			commit('updateCardProperty', { property: 'description', card: updatedCard })
		},
		async updateCardDue({ commit }, card) {
			const updatedCard = apiClient.updateCard(card)
			commit('updateCardProperty', { property: 'duedate', card: updatedCard })
		},
	},
}
