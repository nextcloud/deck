/**
 * SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { createApp, h } from 'vue'
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

			const cleanup = () => {
				if (app) {
					app.unmount()
				}
				container.remove()
			}

			const onClose = () => {
				cleanup()
				reject(new Error('Canceled'))
			}

			const onSelect = async (id) => {
				try {
					const result = await createShare({
						path: self.fileInfo.path + '/' + self.fileInfo.name,
						shareType: 12,
						shareWith: '' + id,
					})
					cleanup()
					resolve(result.data.ocs.data)
				} catch (error) {
					cleanup()
					reject(error)
				}
			}

			const app = createApp({
				render() {
					return h(CardSelector, {
						title: t('deck', 'Share {file} with a Deck card', { file: decodeURIComponent(self.fileInfo.name) }),
						action: t('deck', 'Share'),
						onClose,
						onSelect,
					})
				},
			})
			app.mount(container)
		})
	},
	condition: self => {
		return !!OC.appswebroots.deck
	},
}
