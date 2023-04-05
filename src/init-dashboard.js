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

import './css/dashboard.scss'

const debug = process.env.NODE_ENV !== 'production'

// eslint-disable-next-line
__webpack_nonce__ = btoa(OC.requestToken);
// eslint-disable-next-line
__webpack_public_path__ = OC.linkTo('deck', 'js/');

document.addEventListener('DOMContentLoaded', () => {
	OCA.Dashboard.register('deck', async (el) => {
		const { default: Vue } = await import('vue')
		const { default: Vuex } = await import('vuex')
		const { default: dashboard } = await import('./store/dashboard.js')

		const { default: Dashboard } = await import('./views/Dashboard.vue')
		Vue.prototype.t = t
		Vue.prototype.n = n
		Vue.prototype.OC = OC
		Vue.use(Vuex)

		const store = new Vuex.Store({
			modules: {
				dashboard,
			},
			strict: debug,
		})
		const View = Vue.extend(Dashboard)
		const vm = new View({
			propsData: {},
			store,
		}).$mount(el)
		return vm
	})
})
