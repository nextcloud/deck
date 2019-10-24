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
import xmlToTagList from '../helpers/xml'
import Vue from 'vue'

const apiClient = new CommentApi()

export default {
	state: {
		comments: {}
	},
	mutations: {
		addComments(state, commentObj) {
			if (state.comments[commentObj.cardId] === undefined) {
				Vue.set(state.comments, commentObj.cardId, commentObj.comments)
			} else {
				// FIXME append comments once incremental fetching is implemented
				// state.comments[commentObj.cardId].push(...commentObj.comments)
				Vue.set(state.comments, commentObj.cardId, commentObj.comments)
			}
		},
		createComment(state, newComment) {
			if (state.comments[newComment.cardId] === undefined) {
				state.comments[newComment.cardId] = []
			}
			Vue.set(state.comments, newComment.cardId, newComment)
		},
		updateComment(state, comment) {
			let existingIndex = state.comments[comment.cardId].findIndex(_comment => _comment.id === comment.commentId)
			if (existingIndex !== -1) {
				Vue.set(state.comments[comment.cardId][existingIndex], 'message', comment.comment)
			}
		},
		deleteComment(state, comment) {
			let existingIndex = state.comments[comment.cardId].findIndex(_comment => _comment.id === comment.commentId)
			if (existingIndex !== -1) {
				state.comments[comment.cardId].splice(existingIndex, 1)
			}
		}
	},
	actions: {
		listComments({ commit }, card) {
			apiClient.listComments(card)
				.then((comments) => {
					const commentsJson = xmlToTagList(comments)
					let returnObj = {
						cardId: card.id,
						comments: commentsJson
					}
					commit('addComments', returnObj)
				})
		},
		createComment({ commit }, newComment) {
			apiClient.createComment(newComment)
				.then((newComment) => {
					commit('createComment', newComment)
				})
		},
		deleteComment({ commit }, data) {
			apiClient.deleteComment(data)
				.then((retVal) => {
					commit('deleteComment', data)
				})
		},
		updateComment({ commit }, data) {
			apiClient.updateComment(data)
				.then((retVal) => {
					commit('updateComment', data)
				})
		}
	}
}
