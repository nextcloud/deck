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
		createAttachment(state, { cardId, attachments }) {
			if (typeof state.attachments[cardId] === 'undefined') {
				Vue.set(state.attachments, cardId, attachments)
			} else {
				state.attachments[cardId].push(attachments)
			}
		},

		deleteAttachment(state, deletedAttachment) {
			const existingIndex = state.attachments[deletedAttachment.cardId].findIndex(a => a.id === deletedAttachment.id)
			if (existingIndex !== -1) {
				state.attachments[deletedAttachment.cardId].splice(existingIndex, 1)
			}
		},

	},
	actions: {
		async fetchAttachments({ commit }, cardId) {
			const attachments = await apiClient.fetchAttachments(cardId)
			commit('createAttachment', { cardId, attachments })
		},

		async createAttachment({ commit }, { cardId, formData }) {
			const attachments = await apiClient.createAttachment({ cardId, formData })
			commit('createAttachment', { cardId, attachments })
			commit('cardIncreaseAttachmentCount', cardId)
		},

		async deleteAttachment({ commit }, attachment) {
			await apiClient.deleteAttachment(attachment)
			commit('deleteAttachment', attachment)
			commit('cardDecreaseAttachmentCount', attachment.cardId)
		},

	},
}
