/*
 * @copyright Copyright (c) 2019 Julius Härtl <jus@bitgrid.net>
 *
 * @author Julius Härtl <jus@bitgrid.net>
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

import Vue from 'vue'

import './../css/collections.css'
import FileSharingPicker from './views/FileSharingPicker.js'
import { buildSelector } from './helpers/selector.js'

import './shared-init.js'

Vue.prototype.t = t
Vue.prototype.n = n
Vue.prototype.OC = OC

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
