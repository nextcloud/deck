/**
 * SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { AttachmentApi } from './../services/AttachmentApi.js'

const apiClient = new AttachmentApi()

export default {
	state: {
		attachments: {},
	},
	getters: {
		attachmentsByCard: state => (cardId) => {
			if (typeof state.attachments[cardId] === 'undefined') {
				return []
			}
			return state.attachments[cardId]
		},

	},
	mutations: {
		createAttachment(state, { cardId, attachment }) {
			if (typeof state.attachments[cardId] === 'undefined') {
				state.attachments[cardId] = [attachment]
			} else {
				state.attachments[cardId].push(attachment)
			}
		},

		createAttachments(state, { cardId, attachments }) {
			state.attachments[cardId] = attachments
		},

		updateAttachment(state, { cardId, attachment }) {
			const existingIndex = state.attachments[attachment.cardId].findIndex(a => a.id === attachment.id && a.type === attachment.type)
			if (existingIndex !== -1) {
				state.attachments[cardId][existingIndex] = attachment
			}
		},

		deleteAttachment(state, deletedAttachment) {
			const existingIndex = state.attachments[deletedAttachment.cardId].findIndex(a => a.id === deletedAttachment.id && a.type === deletedAttachment.type)
			if (existingIndex !== -1) {
				state.attachments[deletedAttachment.cardId][existingIndex].deletedAt = Date.now() / 1000 | 0
			}
		},

		unshareAttachment(state, deletedAttachment) {
			const existingIndex = state.attachments[deletedAttachment.cardId].findIndex(a => a.id === deletedAttachment.id && a.type === deletedAttachment.type)
			if (existingIndex !== -1) {
				state.attachments[deletedAttachment.cardId][existingIndex].deletedAt = -1
			}
		},

		restoreAttachment(state, restoredAttachment) {
			const existingIndex = state.attachments[restoredAttachment.cardId].findIndex(a => a.id === restoredAttachment.id && a.type === restoredAttachment.type)
			if (existingIndex !== -1) {
				state.attachments[restoredAttachment.cardId][existingIndex].deletedAt = 0
			}
		},

	},
	actions: {
		async fetchAttachments({ commit }, cardId) {
			const attachments = await apiClient.fetchAttachments(cardId)
			commit('createAttachments', { cardId, attachments })
			commit('cardSetAttachmentCount', { cardId, count: attachments.length })
		},

		async createAttachment({ commit }, { cardId, formData, onUploadProgress }) {
			const attachment = await apiClient.createAttachment({ cardId, formData, onUploadProgress })
			commit('createAttachment', { cardId, attachment })
			commit('cardIncreaseAttachmentCount', cardId)
		},

		async updateAttachment({ commit }, { cardId, attachment, formData }) {
			const result = await apiClient.updateAttachment({ cardId, attachment, formData })
			commit('updateAttachment', { cardId, attachment: result })
		},

		async deleteAttachment({ commit }, attachment) {
			await apiClient.deleteAttachment(attachment)
			commit('deleteAttachment', attachment)
			commit('cardDecreaseAttachmentCount', attachment.cardId)
		},

		async unshareAttachment({ commit }, attachment) {
			await apiClient.deleteAttachment(attachment)
			commit('unshareAttachment', attachment)
			commit('cardDecreaseAttachmentCount', attachment.cardId)
		},

		async restoreAttachment({ commit }, attachment) {
			const restoredAttachment = await apiClient.restoreAttachment(attachment)
			commit('restoreAttachment', restoredAttachment)
			commit('cardIncreaseAttachmentCount', attachment.cardId)
		},

	},
}
