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

const apiClient = new CommentApi()

export default {
	state: {
		comments: []
	},
	mutations: {
		listComments(state, comment) {
			state.comments = []
			state.comments = comment
		},
		updateComment(state, comment) {
			let existingIndex = state.comments.findIndex(_comment => _comment.id === comment.commentId)
			if (existingIndex !== -1) {
				state.comments[existingIndex].message = comment.comment
			}
		}
	},
	actions: {
		listComments({ commit }, card) {
			apiClient.listComments(card.id)
				.then((comments) => {
					let commentsJson = xmlToTagList(comments)
					commit('listComments', commentsJson)
				})
		},
		createComment({ commit }, card) {
			apiClient.createComment(card)
				.then((comments) => {

				})
		},
		deleteComment({ commit }, data) {
			apiClient.deleteComment(data)
				.then((retVal) => {

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
