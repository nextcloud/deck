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
		url = `dav/comments/deckCard/${url}`
		return OC.linkToRemote(url)
	}

	listComments(cardId) {
		return axios({
			method: 'REPORT',
			url: this.url(`${cardId}`),
			data: `<?xml version="1.0" encoding="utf-8" ?>
			<oc:filter-comments xmlns:D="DAV:" xmlns:oc="http://owncloud.org/ns">
				<oc:limit>21</oc:limit>
				<oc:offset>0</oc:offset>
			</oc:filter-comments>`
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

	createComment(card) {
		return axios({
			method: 'POST',
			url: this.url(`${card.id}`),
			data: { actorType: 'users', message: `${card.comment}`, verb: 'comment' }
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

	updateComment(data) {
		return axios({
			method: 'PROPPATCH',
			url: this.url(`${data.cardId}/${data.commentId}`),
			data: `<?xml version="1.0"?>
			<d:propertyupdate xmlns:d="DAV:" xmlns:oc="http://owncloud.org/ns">
				<d:set>
					<d:prop>
						<oc:message>${data.comment}</oc:message>
					</d:prop>
				</d:set>
			</d:propertyupdate>`
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

	deleteComment(data) {
		return axios({
			method: 'DELETE',
			url: this.url(`${data.cardId}/${data.commentId}`)
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

}
