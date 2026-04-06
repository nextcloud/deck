/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { defineStore } from 'pinia'
import { CommentApi } from '../services/CommentApi.js'

const apiClient = new CommentApi()

const COMMENT_FETCH_LIMIT = 10

export const useCommentStore = defineStore('comment', {
	state: () => ({
		comments: {},
		replyTo: null,
	}),
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
	actions: {
		endReached(cardId) {
			if (this.comments[cardId]) {
				this.comments[cardId].hasMore = false
			}
		},
		addComments({ comments, cardId }) {
			if (this.comments[cardId] === undefined) {
				this.comments[cardId] = {
					hasMore: comments.length > 0,
					comments: [...comments],
				}
			} else {
				const newComments = comments.filter((comment) => {
					return this.comments[cardId].comments.findIndex((item) => item.id === comment.id) === -1
				})
				this.comments[cardId].comments.push(...newComments)
			}
		},
		updateComment({ cardId, comment }) {
			const existingIndex = this.comments[cardId].comments.findIndex(c => c.id === comment.id)
			if (existingIndex !== -1) {
				Object.assign(this.comments[cardId].comments[existingIndex], comment)
			}
		},
		deleteComment(comment) {
			const existingIndex = this.comments[comment.cardId].comments.findIndex(_comment => _comment.id === comment.id)
			if (existingIndex !== -1) {
				this.comments[comment.cardId].comments.splice(existingIndex, 1)
			}
		},
		markCommentsAsRead(cardId) {
			this.comments[cardId].comments.forEach(_comment => {
				_comment.isUnread = false
			})
		},
		setReplyTo(comment) {
			this.replyTo = comment
		},
		async fetchComments({ cardId, offset }) {
			const comments = await apiClient.loadComments({
				cardId,
				limit: COMMENT_FETCH_LIMIT,
				offset: offset || 0,
			})

			this.addComments({ cardId, comments })

			if (comments.length < COMMENT_FETCH_LIMIT) {
				this.endReached(cardId)
			}
		},
		async fetchMore({ cardId }) {
			// fetch newer comments first
			await this.fetchComments({ cardId })
			await this.fetchComments({ cardId, offset: this.getCommentsForCard(cardId).length })
		},
		async createComment({ cardId, comment }) {
			await apiClient.createComment({ cardId, comment, replyTo: this.replyTo })
			await this.fetchComments({ cardId })
		},
		async apiDeleteComment(data) {
			await apiClient.deleteComment(data)
			this.deleteComment(data)
		},
		async apiUpdateComment(data) {
			const comment = await apiClient.updateComment(data)
			this.updateComment({ cardId: data.cardId, comment })
		},
		async apiMarkCommentsAsRead(cardId) {
			await apiClient.markCommentsAsRead(cardId)
			this.markCommentsAsRead(cardId)
		},
	},
})
