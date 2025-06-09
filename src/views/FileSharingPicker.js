/**
 * SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import Vue from 'vue'
import { createShare } from '../services/SharingApi.js'

export default {
	icon: 'icon-deck',
	displayName: t('deck', 'Share with a Deck card'),
	handler: async self => {

		return new Promise((resolve, reject) => {
			const container = document.createElement('div')
			container.id = 'deck-board-select'
			const body = document.getElementById('body-user')
			body.append(container)
			const CardSelector = () => import('./../CardSelector.vue')
			const ComponentVM = new Vue({
				render: (h) => h(CardSelector, {
					title: t('deck', 'Share {file} with a Deck card', { file: decodeURIComponent(self.fileInfo.name) }),
					action: t('deck', 'Share'),
				}),
			})
			ComponentVM.$mount(container)
			ComponentVM.$root.$on('close', () => {
				ComponentVM.$el.remove()
				ComponentVM.$destroy()
				reject(new Error('Canceled'))
			})
			ComponentVM.$root.$on('select', async (id) => {
				const result = await createShare({
					path: self.fileInfo.path + '/' + self.fileInfo.name,
					shareType: 12,
					shareWith: '' + id,
				})
				ComponentVM.$el.remove()
				ComponentVM.$destroy()
				resolve(result.data.ocs.data)
			})

		})
	},
	condition: self => {
		return !!OC.appswebroots.deck
	},
}
