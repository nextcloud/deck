/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { listen } from '@nextcloud/notify_push'

// notify_push has no unsubscribe API; register once and retain only live embeds.
const contexts = new Set()
let initialized = false
let pushEnabled = false

export function registerBoardUpdates(context) {
	if (!initialized) {
		initialized = true
		pushEnabled = listen('deck_board_update', (_, event) => {
			for (const active of contexts) {
				if (event.id === active.boardId) {
					active.refreshBoard()
				}
			}
		})
		listen('deck_card_update', (_, event) => {
			for (const active of contexts) {
				if (event.boardId === active.boardId) {
					active.refreshCards()
				}
			}
		})
	}
	contexts.add(context)
	return {
		pushEnabled,
		dispose: () => contexts.delete(context),
	}
}
