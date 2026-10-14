/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { expect, jest, test } from '@jest/globals'
import { listen } from '@nextcloud/notify_push'
import { registerBoardUpdates } from './push.js'

jest.mock('@nextcloud/notify_push', () => ({ listen: jest.fn().mockReturnValue(true) }), { virtual: true })

test('registers once and only refreshes live matching instances', () => {
	const first = { boardId: 42, refreshBoard: jest.fn(), refreshCards: jest.fn() }
	const second = { boardId: 43, refreshBoard: jest.fn(), refreshCards: jest.fn() }
	const a = registerBoardUpdates(first)
	const b = registerBoardUpdates(second)
	expect(a.pushEnabled).toBe(true)
	expect(listen).toHaveBeenCalledTimes(2)
	const boardUpdate = listen.mock.calls[0][1]
	const cardUpdate = listen.mock.calls[1][1]
	boardUpdate('deck_board_update', { id: 42 })
	cardUpdate('deck_card_update', { boardId: 43 })
	expect(first.refreshBoard).toHaveBeenCalledTimes(1)
	expect(second.refreshBoard).not.toHaveBeenCalled()
	expect(second.refreshCards).toHaveBeenCalledTimes(1)
	a.dispose()
	boardUpdate('deck_board_update', { id: 42 })
	expect(first.refreshBoard).toHaveBeenCalledTimes(1)
	b.dispose()
	cardUpdate('deck_card_update', { boardId: 43 })
	expect(second.refreshCards).toHaveBeenCalledTimes(1)
})
