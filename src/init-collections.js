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
import FileSharingPicker from './views/FileSharingPicker'
import { buildSelector } from './helpers/selector'

// eslint-disable-next-line
__webpack_nonce__ = btoa(OC.requestToken);
// eslint-disable-next-line
__webpack_public_path__ = OC.linkTo('deck', 'js/');

Vue.prototype.t = t
Vue.prototype.n = n
Vue.prototype.OC = OC

window.addEventListener('DOMContentLoaded', () => {
	if (OCA.Sharing && OCA.Sharing.ShareSearch) {
		OCA.Sharing.ShareSearch.addNewResult(FileSharingPicker)
	} else {
		console.error('OCA.Sharing.ShareSearch not ready')
	}

	window.OCP.Collaboration.registerType('deck', {
		action: () => {
			const BoardSelector = () => import('./BoardSelector')
			return buildSelector(BoardSelector)
		},
		typeString: t('deck', 'Link to a board'),
		typeIconClass: 'icon-deck',
	})

	window.OCP.Collaboration.registerType('deck-card', {
		action: () => {
			const CardSelector = () => import('./CardSelector')
			return buildSelector(CardSelector)
		},
		typeString: t('deck', 'Link to a card'),
		typeIconClass: 'icon-deck',
	})
})
