/**
 * SPDX-FileCopyrightText: 2018 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'
import '../models/index.js'

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
				},
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
				},
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
				},
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
				},
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
				},
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
				},
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
				},
			)
			.catch((err) => {
				return Promise.reject(err)
			})
	}

}
