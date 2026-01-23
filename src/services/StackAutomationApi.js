/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'

export class StackAutomationApi {
	url(url) {
		url = `/apps/deck${url}`
		return generateUrl(url)
	}

	loadAutomations(stackId) {
		return axios.get(this.url(`/stacks/${stackId}/automations`))
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

	createAutomation(stackId, event, actionType, config, order = 0) {
		return axios.post(this.url(`/stacks/${stackId}/automations`), {
			event,
			actionType,
			config,
			order,
		})
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

	updateAutomation(id, event, actionType, config, order) {
		return axios.put(this.url(`/automations/${id}`), {
			event,
			actionType,
			config,
			order,
		})
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

	deleteAutomation(id) {
		return axios.delete(this.url(`/automations/${id}`))
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
