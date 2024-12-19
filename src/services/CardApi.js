/**
 * SPDX-FileCopyrightText: 2018 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import axios from '@nextcloud/axios'
import { generateOcsUrl, generateUrl } from '@nextcloud/router'

export class CardApi {

	url(url) {
		url = `/apps/deck${url}`
		return generateUrl(url)
	}

	addCard(card) {
		return axios.post(this.url('/cards'), card)
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

	cloneCard(cardId, targetStackId) {
		return axios.post(generateOcsUrl(`apps/deck/api/v1.0/cards/${cardId}/clone`), {
			targetStackId,
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

	deleteCard(cardId) {
		return axios.delete(this.url(`/cards/${cardId}`))
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

	deletedCards(boardId) {
		return axios.get(this.url(`/${boardId}/cards/deleted`))
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

	updateCard(card) {
		return axios.put(this.url(`/cards/${card.id}`), card)
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

	reorderCard(card) {
		return axios.put(this.url(`/cards/${card.id}/reorder`), card)
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

	assignUser(cardId, id, type) {
		return axios.post(this.url(`/cards/${cardId}/assign`), { userId: id, type })
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

	removeUser(cardId, id, type) {
		return axios.put(this.url(`/cards/${cardId}/unassign`), { userId: id, type })
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

	archiveCard(card) {
		return axios.put(this.url(`/cards/${card.id}/archive`))
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

	unArchiveCard(card) {
		return axios.put(this.url(`/cards/${card.id}/unarchive`))
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

	markCardAsDone(card) {
		return axios.put(this.url(`/cards/${card.id}/done`))
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

	markCardAsUndone(card) {
		return axios.put(this.url(`/cards/${card.id}/undone`))
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

	assignLabelToCard(data) {
		return axios.post(this.url(`/cards/${data.card.id}/label/${data.labelId}`))
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

	removeLabelFromCard(data) {
		return axios.delete(this.url(`/cards/${data.card.id}/label/${data.labelId}`))
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
