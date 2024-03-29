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

import { subscribe } from '@nextcloud/event-bus'
import { generateUrl } from '@nextcloud/router'

import './shared-init.js'

subscribe('calendar:handle-todo-click', ({ calendarId, taskId }) => {
	const deckAppPrefix = 'app-generated--deck--board-'
	if (calendarId.startsWith(deckAppPrefix)) {
		const board = calendarId.slice(deckAppPrefix.length)
		const card = taskId.slice('card-'.length).replace('.ics', '')
		console.debug('[deck] Clicked task matches deck calendar pattern')
		window.location = generateUrl(`apps/deck/#/board/${board}/card/${card}`)
	}
})
