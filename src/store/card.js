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
import moment from 'moment'
import Vue from 'vue'

const apiClient = new CardApi()

export default {
	state: {
		cards: [],
	},
	getters: {
		cardsByStack: (state, getters, rootState) => (id) => {
			return state.cards.filter((card) => {
				const { tags, users, due, unassigned } = rootState.filter
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

				if (unassigned && card.assignedUsers.length > 0) {
					return false
				}

				if (due !== '') {
					const datediffHour = ((new Date(card.duedate) - new Date()) / 3600 / 1000)
					switch (due) {
					case 'noDue':
						return (card.duedate === null)
					case 'overdue':
						return (card.overdue === 3)
					case 'dueToday':
						return (card.overdue >= 2)
					case 'dueWeek':
						return (datediffHour <= 7 * 24 && card.duedate !== null)
					case 'dueMonth':
						return (datediffHour <= 30 * 24 && card.duedate !== null)
					}
				}

				return true
			})
				.filter((card) => card.stackId === id)
				.filter((card) => {
					if (getters.getSearchQuery === '') {
						return true
					}

					let hasMatch = true
					const matches = getters.getSearchQuery.match(/(?:[^\s"]+|"[^"]*")+/g)

					const filterOutQuotes = (q) => {
						if (q[0] === '"' && q[q.length - 1] === '"') {
							return q.slice(1, -1)
						}
						return q
					}
					for (const match of matches) {
						let [filter, query] = match.indexOf(':') !== -1 ? match.split(/:(.*)/) : [null, match]
						const isEmptyQuery = typeof query === 'undefined' || filterOutQuotes(query) === ''

						if (filter === 'title') {
							if (isEmptyQuery) {
								continue
							}
							hasMatch = hasMatch && card.title.toLowerCase().includes(filterOutQuotes(query).toLowerCase())
						} else if (filter === 'description') {
							if (isEmptyQuery) {
								hasMatch = hasMatch && !!card.description
								continue
							}
							hasMatch = hasMatch && card.description.toLowerCase().includes(filterOutQuotes(query).toLowerCase())
						} else if (filter === 'list') {
							if (isEmptyQuery) {
								continue
							}
							const stack = getters.stackById(card.stackId)
							if (!stack) {
								return false
							}
							hasMatch = hasMatch && stack.title.toLowerCase().includes(filterOutQuotes(query).toLowerCase())
						} else if (filter === 'tag') {
							if (isEmptyQuery) {
								hasMatch = hasMatch && card.labels.length > 0
								continue
							}
							hasMatch = hasMatch && card.labels.findIndex((label) => label.title.toLowerCase().includes(filterOutQuotes(query).toLowerCase())) !== -1
						} else if (filter === 'date') {
							const datediffHour = ((new Date(card.duedate) - new Date()) / 3600 / 1000)
							query = filterOutQuotes(query)
							switch (query) {
							case 'overdue':
								hasMatch = hasMatch && (card.overdue === 3)
								break
							case 'today':
								hasMatch = hasMatch && (datediffHour > 0 && datediffHour <= 24 && card.duedate !== null)
								break
							case 'week':
								hasMatch = hasMatch && (datediffHour > 0 && datediffHour <= 7 * 24 && card.duedate !== null)
								break
							case 'month':
								hasMatch = hasMatch && (datediffHour > 0 && datediffHour <= 30 * 24 && card.duedate !== null)
								break
							case 'none':
								hasMatch = hasMatch && (card.duedate === null)
								break
							}

							if (card.duedate === null || !hasMatch) {
								return false
							}
							const comparator = query[0] + (query[1] === '=' ? '=' : '')
							const isValidComparator = ['<', '<=', '>', '>='].indexOf(comparator) !== -1
							const parsedCardDate = moment(card.duedate)
							const parsedDate = moment(query.slice(isValidComparator ? comparator.length : 0))
							switch (comparator) {
							case '<':
								hasMatch = hasMatch && parsedCardDate.isBefore(parsedDate)
								break
							case '<=':
								hasMatch = hasMatch && parsedCardDate.isSameOrBefore(parsedDate)
								break
							case '>':
								hasMatch = hasMatch && parsedCardDate.isAfter(parsedDate)
								break
							case '>=':
								hasMatch = hasMatch && parsedCardDate.isSameOrAfter(parsedDate)
								break
							default:
								hasMatch = hasMatch && parsedCardDate.isSame(parsedDate)
								break
							}

						} else if (filter === 'assigned') {
							if (isEmptyQuery) {
								hasMatch = hasMatch && card.assignedUsers.length > 0
								continue
							}
							hasMatch = hasMatch && card.assignedUsers.findIndex((assignment) => {
								return assignment.participant.primaryKey.toLowerCase() === filterOutQuotes(query).toLowerCase()
									|| assignment.participant.displayname.toLowerCase() === filterOutQuotes(query).toLowerCase()
							}) !== -1
						} else {
							hasMatch = hasMatch && (card.title.toLowerCase().includes(filterOutQuotes(match).toLowerCase())
								|| card.description.toLowerCase().includes(filterOutQuotes(match).toLowerCase()))
						}
						if (!hasMatch) {
							return false
						}
					}
					return true
				})
				.sort((a, b) => a.order - b.order || a.createdAt - b.createdAt)
		},
		cardById: state => (id) => {
			return state.cards.find((card) => card.id === id)
		},
	},
	mutations: {
		addCard(state, card) {
			card.labels = card.labels || []
			card.assignedUsers = card.assignedUsers || []
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
					Vue.set(state.cards[existingIndex], 'order', newCard.order)
					Vue.set(state.cards[existingIndex], 'stackId', newCard.stackId)
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
				Vue.set(state.cards[existingIndex], 'lastModified', Date.now() / 1000)
			}
		},
		cardSetAttachmentCount(state, { cardId, count }) {
			const existingIndex = state.cards.findIndex(_card => _card.id === cardId)
			if (existingIndex !== -1) {
				Vue.set(state.cards[existingIndex], 'attachmentCount', count)
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
		addNewCard(state, card) {
			state.cards.push(card)
		},
	},
	actions: {
		async addCard({ commit }, card) {
			const createdCard = await apiClient.addCard(card)
			commit('addCard', createdCard)
			return createdCard
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
			const newCards = []
			for (const c of getters.cardsByStack(card.stackId)) {
				if (c.id === card.id) {
					newCards.push(card)
				}
				if (i === card.order) {
					i++
				}
				if (c.id !== card.id) {
					newCards.push({ ...c, order: i++ })
				}
			}
			newCards.push(card)
			await commit('updateCardsReorder', newCards)

			apiClient.reorderCard(card).then((cards) => {
				commit('updateCardsReorder', Object.values(cards))
			})
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
			commit('updateCard', updatedCard)
		},
		async assignCardToUser({ commit }, { card, assignee }) {
			const user = await apiClient.assignUser(card.id, assignee.userId, assignee.type)
			commit('assignCardToUser', user)
		},
		async removeUserFromCard({ commit }, { card, assignee }) {
			const user = await apiClient.removeUser(card.id, assignee.userId, assignee.type)
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
			const updatedCard = await apiClient.updateCard(card)
			commit('updateCardProperty', { property: 'duedate', card: updatedCard })
		},

		addCardData({ commit }, cardData) {
			const card = { ...cardData }
			commit('addStack', card.relatedStack)
			commit('addBoard', card.relatedBoard)
			delete card.relatedStack
			delete card.relatedBoard
			commit('addCard', card)
		},
	},
}
