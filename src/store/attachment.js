/*
 * @copyright Copyright (c) 2020 Jakob Röhrl <jakob.roehrl@web.de>
 *
 * @author Jakob Röhrl <jakob.roehrl@web.de>
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

import { AttachmentApi } from './../services/AttachmentApi'
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
			if (typeof state.attachments[cardId] === 'undefined') {
				Vue.set(state.attachments, cardId, attachments)
			} else {
				state.attachments[cardId].push(attachments)
			}
		},

		updateAttachment(state, { cardId, attachment }) {
			const existingIndex = state.attachments[attachment.cardId].findIndex(a => a.id === attachment.id)
			if (existingIndex !== -1) {
				Vue.set(state.attachments[cardId], existingIndex, attachment)
			}
		},

		deleteAttachment(state, deletedAttachment) {
			const existingIndex = state.attachments[deletedAttachment.cardId].findIndex(a => a.id === deletedAttachment.id)
			if (existingIndex !== -1) {
				state.attachments[deletedAttachment.cardId][existingIndex].deletedAt = -1
			}
		},

		restoreAttachment(state, restoredAttachment) {
			const existingIndex = state.attachments[restoredAttachment.cardId].findIndex(a => a.id === restoredAttachment.id)
			if (existingIndex !== -1) {
				state.attachments[restoredAttachment.cardId][existingIndex].deletedAt = 0
			}
		},

	},
	actions: {
		async fetchAttachments({ commit }, cardId) {
			const attachments = await apiClient.fetchAttachments(cardId)
			commit('createAttachments', { cardId, attachments })
		},

		async createAttachment({ commit }, { cardId, formData, onUploadProgress }) {
			const attachment = await apiClient.createAttachment({ cardId, formData, onUploadProgress })
			commit('createAttachment', { cardId, attachment })
			commit('cardIncreaseAttachmentCount', cardId)
		},

		async updateAttachment({ commit }, { cardId, attachmentId, formData }) {
			const attachment = await apiClient.updateAttachment({ cardId, attachmentId, formData })
			commit('updateAttachment', { cardId, attachment })
		},

		async deleteAttachment({ commit }, attachment) {
			await apiClient.deleteAttachment(attachment)
			commit('deleteAttachment', attachment)
			commit('cardDecreaseAttachmentCount', attachment.cardId)
		},

		async restoreAttachment({ commit }, attachment) {
			const restoredAttachment = await apiClient.restoreAttachment(attachment)
			commit('restoreAttachment', restoredAttachment)
			commit('cardIncreaseAttachmentCount', attachment.cardId)
		},

	},
}
