/*
 * @copyright Copyright (c) 2018 Michael Weimann <mail@michael-weimann.eu>
 *
 * @author Michael Weimann <mail@michael-weimann.eu>
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

import axios from 'nextcloud-axios'

export class CommentApi {

	url(url) {
		url = `/remote.php/dav/comments/deckCard/${url}`
		return OC.generateUrl(url)
	}

	listComments(cardId) {

		return axios({
			method: 'REPORT',
			url: this.url(`${cardId}`)
		}).then(
			(response) => {
				return Promise.resolve(response.data)
			},
			(err) => {
				return Promise.reject(err)
			}
		)
			.catch((err) => {
				return Promise.reject(err)
			})
	}

	createComment(cardId) {
		return axios.post(this.url(`${cardId}`))
			.then(
				(response) => {
					return Promise.resolve(response.data)
				},
				(err) => {
					return Promise.reject(err)
				}
			)
			.catch((err) => {
				return Promise.reject(err)
			})
	}

	updateComment(cardId, commentId) {
		return axios.proppatch(this.url(`${cardId}/${commentId}`))
			.then(
				(response) => {
					return Promise.resolve(response.data)
				},
				(err) => {
					return Promise.reject(err)
				}
			)
			.catch((err) => {
				return Promise.reject(err)
			})
	}

	deleteComment(cardId, commentId) {
		return axios.delete(this.url(`${cardId}/${commentId}`))
			.then(
				(response) => {
					return Promise.resolve(response.data)
				},
				(err) => {
					return Promise.reject(err)
				}
			)
			.catch((err) => {
				return Promise.reject(err)
			})
	}

}
