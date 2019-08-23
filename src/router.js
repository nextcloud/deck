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
import { BOARD_FILTERS } from './store/main'
import Boards from './components/boards/Boards'
import Board from './components/board/Board'
import Sidebar from './components/Sidebar'
import BoardSidebar from './components/board/BoardSidebar'
import CardSidebar from './components/card/CardSidebar'

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
		},
		{
			path: '/boards/:id',
			name: 'board',
			components: {
				default: Board,
				sidebar: Sidebar
			},
			props: {
				default: (route) => {
					return {
						id: parseInt(route.params.id, 10)
					}
				}
			},
			children: [
				{
					path: 'details',
					name: 'board.details',
					components: {
						default: Boards,
						sidebar: BoardSidebar
					},
					props: {
						default: (route) => {
							return {
								id: parseInt(route.params.id, 10)
							}
						},
						sidebar: (route) => {
							return {
								id: parseInt(route.params.id, 10)
							}
						}
					}
				},
				{
					path: 'cards/:cardId',
					name: 'card',
					components: {
						sidebar: CardSidebar
					},
					props: {
						sidebar: (route) => {
							return {
								id: parseInt(route.params.cardId, 10)
							}
						}
					}
				}
			]
		}
	]
})
