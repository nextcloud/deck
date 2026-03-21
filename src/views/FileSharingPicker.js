/**
 * SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { createShare } from '../services/SharingApi.js'
import { buildSelector } from '../helpers/selector.js'

export default {
	icon: 'icon-deck',
	displayName: t('deck', 'Share with a Deck card'),
	handler: async self => {
		const CardSelector = () => import('./../CardSelector.vue')
		const id = await buildSelector(CardSelector, {
			props: {
				title: t('deck', 'Share {file} with a Deck card', { file: decodeURIComponent(self.fileInfo.name) }),
				action: t('deck', 'Share'),
			},
			rejectMessage: 'Canceled',
		})

		const result = await createShare({
			path: self.fileInfo.path + '/' + self.fileInfo.name,
			shareType: 12,
			shareWith: '' + id,
		})

		return result.data.ocs.data
	},
	condition: self => {
		return !!OC.appswebroots.deck
	},
}
