/*
 * @copyright Copyright (c) 2018 Julius Härtl <jus@bitgrid.net>
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
import Router from 'vue-router'
import { generateUrl } from 'nextcloud-server/dist/router'
import { BOARD_FILTERS } from './store/modules/nav'

const Boards = () => import('./components/Boards')

Vue.use(Router)

export default new Router({
	base: generateUrl('/apps/deck/'),
	linkActiveClass: 'active',
	routes: [
		{
			path: '/',
			name: 'main',
			component: Boards
		},
		{
			path: '/boards',
			name: 'boards',
			component: Boards,
			props: {
				navFilter: BOARD_FILTERS.ALL
			}
		},
		{
			path: '/boards/archived',
			name: 'boards.archived',
			component: Boards,
			props: {
				navFilter: BOARD_FILTERS.ARCHIVED
			}
		},
		{
			path: '/boards/shared',
			name: 'boards.shared',
			component: Boards,
			props: {
				navFilter: BOARD_FILTERS.SHARED
			}
		}
	]
})
