/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { beforeEach, expect, jest, test } from '@jest/globals'
import Board from '../components/board/Board.vue'
import BoardReferenceWidget from './BoardReferenceWidget.vue'
import { BoardApi } from '../services/BoardApi.js'
import { useSettingsStore } from '../stores/settings.js'

jest.mock('../components/board/Board.vue', () => ({}))
jest.mock('../components/icons/DeckIcon.vue', () => ({}))
jest.mock('../services/BoardApi.js', () => ({ BoardApi: jest.fn() }))
jest.mock('../stores/settings.js', () => ({ useSettingsStore: jest.fn() }))
jest.mock('@nextcloud/vue', () => ({}), { virtual: true })
jest.mock('@nextcloud/moment', () => ({}), { virtual: true })
jest.mock('@nextcloud/router', () => ({ generateUrl: () => '/apps/deck/#/board/42' }), { virtual: true })

beforeEach(() => {
	useSettingsStore.mockReturnValue({ setFullApp: jest.fn() })
})

test('reference widget keeps the existing board component and API provider', () => {
	expect(BoardReferenceWidget.components.Board).toBe(Board)
	expect(BoardReferenceWidget.provide().boardApi).toBeInstanceOf(BoardApi)
})

test('reference widgets disable full-app behavior', () => {
	BoardReferenceWidget.created()
	expect(useSettingsStore().setFullApp).toHaveBeenCalledWith(false)
})

test('non-interactive reference links to the existing board route', () => {
	expect(BoardReferenceWidget.computed.boardLink.call({ board: { id: 42 } }))
		.toBe('/apps/deck/#/board/42')
})
