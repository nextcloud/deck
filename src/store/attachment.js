/**
 * SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { AttachmentApi } from './../services/AttachmentApi.js'
import Vue from 'vue'

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
				Vue.set(state.attachments, cardId, [attachment])
			} else {
				state.attachments[cardId].push(attachment)
			}
		},

		createAttachments(state, { cardId, attachments }) {
			Vue.set(state.attachments, cardId, attachments)
		},

		updateAttachment(state, { cardId, attachment }) {
			const existingIndex = state.attachments[attachment.cardId].findIndex(a => a.id === attachment.id && a.type === attachment.type)
			if (existingIndex !== -1) {
				Vue.set(state.attachments[cardId], existingIndex, attachment)
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
		async fetchAttachments({ commit, rootState }, cardId) {
			const boardId = rootState.currentBoard.id
			const attachments = await apiClient.fetchAttachments(cardId, boardId)
			commit('createAttachments', { cardId, attachments })
			commit('cardSetAttachmentCount', { cardId, count: attachments.length })
		},

		async createAttachment({ commit, rootState }, { cardId, formData, onUploadProgress }) {
			const boardId = rootState.currentBoard.id
			const attachment = await apiClient.createAttachment({ cardId, formData, onUploadProgress, boardId })
			commit('createAttachment', { cardId, attachment })
			commit('cardIncreaseAttachmentCount', cardId)
		},

		async updateAttachment({ commit, rootState }, { cardId, attachment, formData }) {
			const boardId = rootState.currentBoard.id
			const result = await apiClient.updateAttachment({ cardId, attachment, formData, boardId })
			commit('updateAttachment', { cardId, attachment: result })
		},

		async deleteAttachment({ commit, rootState }, attachment) {
			const boardId = rootState.currentBoard.id
			await apiClient.deleteAttachment(attachment, boardId)
			commit('deleteAttachment', attachment)
			commit('cardDecreaseAttachmentCount', attachment.cardId)
		},

		async unshareAttachment({ commit, rootState }, attachment) {
			const boardId = rootState.currentBoard.id
			await apiClient.deleteAttachment(attachment, boardId)
			commit('unshareAttachment', attachment)
			commit('cardDecreaseAttachmentCount', attachment.cardId)
		},

		async restoreAttachment({ commit, rootState }, attachment) {
			const boardId = rootState.currentBoard.id
			const restoredAttachment = await apiClient.restoreAttachment(attachment, boardId)
			commit('restoreAttachment', restoredAttachment)
			commit('cardIncreaseAttachmentCount', attachment.cardId)
		},

	},
}
