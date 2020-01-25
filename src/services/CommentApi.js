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
import { getCurrentUser } from '@nextcloud/auth'

export class CommentApi {

	url(url) {
		url = `dav/comments/deckCard/${url}`
		return OC.linkToRemote(url)
	}

	listComments(card) {
		return axios({
			method: 'REPORT',
			url: this.url(`${card.id}`),
			data: `<?xml version="1.0" encoding="utf-8" ?>
			<oc:filter-comments xmlns:D="DAV:" xmlns:oc="http://owncloud.org/ns">
				<oc:limit>${card.limit}</oc:limit>
				<oc:offset>${card.offset}</oc:offset>
			</oc:filter-comments>`,
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

	createComment(commentObj) {
		return axios({
			method: 'POST',
			url: this.url(`${commentObj.cardId}`),
			data: { actorType: 'users', message: `${commentObj.comment}`, verb: 'comment' },
		}).then(
			(response) => {
				const header = response.headers['content-location']
				const headerArray = header.split('/')
				const id = headerArray[headerArray.length - 1]

				const ret = {
					cardId: (commentObj.cardId).toString(),
					id: id,
					uId: getCurrentUser().uid,
					creationDateTime: (new Date()).toString(),
					message: commentObj.comment,
				}
				return Promise.resolve(ret)
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
			</d:propertyupdate>`,
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
			url: this.url(`${data.cardId}/${data.commentId}`),
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
