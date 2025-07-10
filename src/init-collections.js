/**
 * SPDX-FileCopyrightText: 2019 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import './../css/collections.css'
import FileSharingPicker from './views/FileSharingPicker.js'
import { buildSelector } from './helpers/selector.js'

import './shared-init.js'

export function initCollections() {
	window.addEventListener('DOMContentLoaded', () => {
		if (OCA.Sharing && OCA.Sharing.ShareSearch) {
			OCA.Sharing.ShareSearch.addNewResult(FileSharingPicker)
		}

		window.OCP.Collaboration.registerType('deck', {
			action: () => {
				const BoardSelector = () => import('./BoardSelector.vue')
				return buildSelector(BoardSelector)
			},
			typeString: t('deck', 'Link to a board'),
			typeIconClass: 'icon-deck',
		})

		window.OCP.Collaboration.registerType('deck-card', {
			action: () => {
				const CardSelector = () => import('./CardSelector.vue')
				return buildSelector(CardSelector)
			},
			typeString: t('deck', 'Link to a card'),
			typeIconClass: 'icon-deck',
		})
	})
}
