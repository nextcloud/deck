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

import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'
import './../models'

export class StackApi {

	url(url) {
		url = `/apps/deck${url}`
		return generateUrl(url)
	}

	loadStacks(boardId) {
		return axios.get(this.url(`/stacks/${boardId}`))
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

	deletedStacks(boardId) {
		return axios.get(this.url(`/${boardId}/stacks/deleted`))
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

	loadArchivedStacks(boardId) {
		return axios.get(this.url(`/stacks/${boardId}/archived`))
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

	/**
	 * @param {Stack} stack stack object to create
	 * @return {Promise}
	 */
	createStack(stack) {
		return axios.post(this.url('/stacks'), stack)
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

	reorderStack(stackId, order) {
		return axios.put(this.url(`/stacks/${stackId}/reorder`), { order })
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

	deleteStack(stackId) {
		return axios.delete(this.url(`/stacks/${stackId}`))
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

	updateStack(stack) {
		return axios.put(this.url(`/stacks/${stack.id}`), stack)
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
