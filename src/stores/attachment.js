/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { defineStore } from 'pinia'
import Vue from 'vue'
import { AttachmentApi } from '../services/AttachmentApi.js'

const apiClient = new AttachmentApi()

export const useAttachmentStore = defineStore('attachment', {
	state: () => ({
		attachments: {},
	}),
	getters: {
		attachmentsByCard: (state) => (cardId) => {
			if (typeof state.attachments[cardId] === 'undefined') {
				return []
			}
			return state.attachments[cardId]
		},
	},
	actions: {
		async createAttachment({ cardId, formData, onUploadProgress }) {
			const boardId = this.$vuex.state.currentBoard.id
			const attachment = await apiClient.createAttachment({ cardId, formData, onUploadProgress, boardId })
			if (typeof this.attachments[cardId] === 'undefined') {
				Vue.set(this.attachments, cardId, [attachment])
			} else {
				this.attachments[cardId].push(attachment)
			}
			this.$vuex.commit('cardIncreaseAttachmentCount', cardId)
		},
		async fetchAttachments(cardId) {
			const boardId = this.$vuex.state.currentBoard.id
			const attachments = await apiClient.fetchAttachments(cardId, boardId)
			Vue.set(this.attachments, cardId, attachments)
			this.$vuex.commit('cardSetAttachmentCount', { cardId, count: attachments.length })
		},
		async updateAttachment({ cardId, attachment, formData }) {
			const boardId = this.$vuex.state.currentBoard.id
			const result = await apiClient.updateAttachment({ cardId, attachment, formData, boardId })
			const existingIndex = this.attachments[attachment.cardId].findIndex(a => a.id === attachment.id && a.type === attachment.type)
			if (existingIndex !== -1) {
				Vue.set(this.attachments[cardId], existingIndex, result)
			}
		},
		async deleteAttachment(attachment) {
			const boardId = this.$vuex.state.currentBoard.id
			await apiClient.deleteAttachment(attachment, boardId)
			const existingIndex = this.attachments[attachment.cardId].findIndex(a => a.id === attachment.id && a.type === attachment.type)
			if (existingIndex !== -1) {
				Vue.set(this.attachments[attachment.cardId][existingIndex], 'deletedAt', Date.now() / 1000 | 0)
			}
			this.$vuex.commit('cardDecreaseAttachmentCount', attachment.cardId)
		},
		async unshareAttachment(attachment) {
			const boardId = this.$vuex.state.currentBoard.id
			await apiClient.deleteAttachment(attachment, boardId)
			const existingIndex = this.attachments[attachment.cardId].findIndex(a => a.id === attachment.id && a.type === attachment.type)
			if (existingIndex !== -1) {
				Vue.set(this.attachments[attachment.cardId][existingIndex], 'deletedAt', -1)
			}
			this.$vuex.commit('cardDecreaseAttachmentCount', attachment.cardId)
		},
		async restoreAttachment(attachment) {
			const boardId = this.$vuex.state.currentBoard.id
			const restoredAttachment = await apiClient.restoreAttachment(attachment, boardId)
			const existingIndex = this.attachments[restoredAttachment.cardId].findIndex(a => a.id === restoredAttachment.id && a.type === restoredAttachment.type)
			if (existingIndex !== -1) {
				Vue.set(this.attachments[restoredAttachment.cardId][existingIndex], 'deletedAt', 0)
			}
			this.$vuex.commit('cardIncreaseAttachmentCount', attachment.cardId)
		},
	},

})
