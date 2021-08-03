/*
 * @copyright Copyright (c) 2020 Julius Härtl <jus@bitgrid.net>
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
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 *
 */

import Vue from 'vue'
import { createShare } from '../services/SharingApi'

export default {
	icon: 'icon-deck',
	displayName: t('deck', 'Share with a Deck card'),
	handler: async self => {

		return new Promise((resolve, reject) => {
			const container = document.createElement('div')
			container.id = 'deck-board-select'
			const body = document.getElementById('body-user')
			body.append(container)
			const CardSelector = () => import('./../CardSelector')
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
	condition: self => true,
}
