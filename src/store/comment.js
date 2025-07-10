/**
 * SPDX-FileCopyrightText: 2018 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { CommentApi } from '../services/CommentApi.js'

const apiClient = new CommentApi()

const COMMENT_FETCH_LIMIT = 10

export default {
	state: {
		comments: {},
		replyTo: null,
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
				state.comments[cardId] = {
					hasMore: comments.length >= 0,
					comments: [...comments],
				}
			} else {
				const newComments = comments.filter((comment) => {
					return state.comments[cardId].comments.findIndex((item) => item.id === comment.id) === -1
				})
				state.comments[cardId].comments.push(...newComments)
			}
		},
		updateComment(state, { cardId, comment }) {
			const existingIndex = state.comments[cardId].comments.findIndex(c => c.id === comment.id)
			if (existingIndex !== -1) {
				Object.assign(state.comments[cardId].comments[existingIndex], comment)
			}
		},
		deleteComment(state, comment) {
			const existingIndex = state.comments[comment.cardId].comments.findIndex(_comment => _comment.id === comment.id)
			if (existingIndex !== -1) {
				state.comments[comment.cardId].comments.splice(existingIndex, 1)
			}
		},
		markCommentsAsRead(state, cardId) {
			state.comments[cardId].comments.forEach(_comment => {
				_comment.isUnread = false
			})
		},
		setReplyTo(state, comment) {
			state.replyTo = comment
		},
	},
	actions: {
		async fetchComments({ commit }, { cardId, offset }) {
			const comments = await apiClient.loadComments({
				cardId,
				limit: COMMENT_FETCH_LIMIT,
				offset: offset || 0,
			})

			commit('addComments', {
				cardId,
				comments,
			})

			if (comments.length < COMMENT_FETCH_LIMIT) {
				commit('endReached', { cardId })

			}
		},
		async fetchMore({ commit, dispatch, getters }, { cardId }) {
			// fetch newer comments first
			await dispatch('fetchComments', { cardId })
			await dispatch('fetchComments', { cardId, offset: getters.getCommentsForCard(cardId).length })

		},
		async createComment({ commit, dispatch, state }, { cardId, comment }) {
			await apiClient.createComment({ cardId, comment, replyTo: state.replyTo })
			await dispatch('fetchComments', { cardId })
		},
		async deleteComment({ commit }, data) {
			await apiClient.deleteComment(data)
			commit('deleteComment', data)
		},
		async updateComment({ commit }, data) {
			const comment = await apiClient.updateComment(data)
			await commit('updateComment', { cardId: data.cardId, comment })
		},
		async markCommentsAsRead({ commit }, cardId) {
			await apiClient.markCommentsAsRead(cardId)
			await commit('markCommentsAsRead', cardId)
		},
		setReplyTo({ commit }, comment) {
			commit('setReplyTo', comment)
		},
	},
}
