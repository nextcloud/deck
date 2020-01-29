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

import { CommentApi } from '../services/CommentApi'
import Vue from 'vue'

const apiClient = new CommentApi()

const COMMENT_FETCH_LIMIT = 3

export default {
	state: {
		comments: {},
	},
	getters: {
		getCommentsForCard: (state) => (id) => {
			if (state.comments[id]) {
				return [...state.comments[id].comments].sort((a, b) => b.id - a.id)
			}
			return []
		},
		hasMoreComments: (state) => (cardId) => {
			return state.comments[cardId] && state.comments[cardId].hasMore
		},
	},
	mutations: {
		endReached(state, { cardId }) {
			if (state.comments[cardId]) {
				state.comments[cardId].hasMore = false
			}
		},
		addComments(state, { comments, cardId }) {
			if (state.comments[cardId] === undefined) {
				Vue.set(state.comments, cardId, {
					hasMore: comments.length > 0,
					comments,
				})
			} else {
				const newComments = comments.filter((comment) => {
					return state.comments[cardId].comments.findIndex((item) => item.id === comment.id) === -1
				})
				state.comments[cardId].comments.push(...newComments)
			}
		},
		updateComment(state, comment) {
			const existingIndex = state.comments[comment.cardId].comments.findIndex(_comment => _comment.id === comment.commentId)
			if (existingIndex !== -1) {
				state.comments[comment.cardId].comments[existingIndex].message = comment.comment
			}
		},
		deleteComment(state, comment) {
			const existingIndex = state.comments[comment.cardId].comments.findIndex(_comment => _comment.id === comment.commentId)
			if (existingIndex !== -1) {
				state.comments[comment.cardId].comments.splice(existingIndex, 1)
			}
		},
	},
	actions: {
		async fetchComments({ commit }, { cardId, offset }) {
			const comments = await apiClient.loadComments({
				cardId,
				limit: COMMENT_FETCH_LIMIT,
				offset: offset || 0,
			})

			if (comments.length < COMMENT_FETCH_LIMIT) {
				commit('endReached', { cardId })
				return
			}
			commit('addComments', {
				cardId,
				comments,
			})
		},
		async fetchMore({ commit, dispatch, getters }, { cardId }) {
			// fetch newer comments first
			await dispatch('fetchComments', { cardId })
			await dispatch('fetchComments', { cardId, offset: getters.getCommentsForCard(cardId).length })

		},
		async createComment({ commit, dispatch }, { cardId, comment }) {
			await apiClient.createComment({ cardId, comment })
			await dispatch('fetchComments', { cardId })
		},
		async deleteComment({ commit }, data) {
			await apiClient.deleteComment(data)
			commit('deleteComment', data)
		},
		async updateComment({ commit }, data) {
			await apiClient.updateComment(data)
			commit('updateComment', data)
		},
	},
}
