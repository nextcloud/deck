/**
 * SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
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
