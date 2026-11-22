/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { describe, expect, it, jest } from '@jest/globals'
import Controls from './Controls.vue'
import { useBoardStore } from '../stores/board.js'

jest.mock('@nextcloud/auth', () => ({}))
jest.mock('@nextcloud/event-bus', () => ({}))
jest.mock('@nextcloud/vue', () => ({}), { virtual: true })
jest.mock('@vueuse/components', () => ({}), { virtual: true })
jest.mock('pinia', () => ({
	mapActions: () => ({}),
	mapState: () => ({}),
	mapWritableState: () => ({}),
}))
jest.mock('../views/CreateNewCardCustomPicker.vue', () => ({}))
jest.mock('./SessionList.vue', () => ({}))
jest.mock('../mixins/labelStyle.js', () => ({}))
jest.mock('../sessions.js', () => ({}))
jest.mock('../stores/board.js', () => ({ useBoardStore: jest.fn() }))
jest.mock('../stores/card.js', () => ({}))
jest.mock('../stores/settings.js', () => ({}))
jest.mock('../stores/stack.js', () => ({}))
jest.mock('vue-material-design-icons/ArchiveOutline.vue', () => ({}))
jest.mock('vue-material-design-icons/ArrowCollapseVertical.vue', () => ({}))
jest.mock('vue-material-design-icons/ArrowExpandVertical.vue', () => ({}))
jest.mock('vue-material-design-icons/ChartGantt.vue', () => ({}))
jest.mock('vue-material-design-icons/FilterOffOutline.vue', () => ({}))
jest.mock('vue-material-design-icons/FilterOutline.vue', () => ({}))
jest.mock('vue-material-design-icons/ImageMultipleOutline.vue', () => ({}))
jest.mock('vue-material-design-icons/TableColumnPlusAfter.vue', () => ({}))
jest.mock('vue-material-design-icons/ViewColumn.vue', () => ({}))

describe('Controls page title', () => {
	it('does not access a router in an embedded widget', () => {
		const context = {
			isFullApp: false,
			board: { title: 'Board' },
			get $route() {
				throw new Error('Widgets do not have a router')
			},
		}
		expect(Controls.computed.pageTitle.call(context)).toBe('')
	})

	it('does not change the host title in widget mode', () => {
		expect(() => Controls.methods.setPageTitle.call({ isFullApp: false }, 'Card - Board')).not.toThrow()
	})

	it('keeps the board title in the full app', () => {
		expect(Controls.computed.pageTitle.call({
			isFullApp: true,
			board: { title: 'Board' },
			$route: { params: {} },
		})).toBe('Board')
	})

	it('keeps the active card title in the full app', () => {
		expect(Controls.computed.pageTitle.call({
			isFullApp: true,
			board: { title: 'Board' },
			$route: { params: { cardId: '7' } },
			cardById: (id) => id === 7 ? { title: 'Card' } : null,
		})).toBe('Card - Board')
	})

	it('handles an unloaded board in the full app', () => {
		expect(Controls.computed.pageTitle.call({ isFullApp: true, board: null })).toBe('')
	})
})

describe('Controls setSwimlaneMode', () => {
	function modeContext(overrides) {
		const setSwimlaneMode = jest.fn()
		useBoardStore.mockReturnValue({ setSwimlaneMode })
		const context = { board: { id: 7 }, canEdit: true, isArchived: false, $pinia: {}, ...overrides }
		return { context, setSwimlaneMode }
	}

	it('writes the mode on an editable board', () => {
		const { context, setSwimlaneMode } = modeContext({})
		Controls.methods.setSwimlaneMode.call(context, 'labels')
		expect(setSwimlaneMode).toHaveBeenCalledWith({ boardId: 7, mode: 'labels' })
	})

	it('does not write the mode on an archived board', () => {
		const { context, setSwimlaneMode } = modeContext({ isArchived: true })
		Controls.methods.setSwimlaneMode.call(context, 'labels')
		expect(setSwimlaneMode).not.toHaveBeenCalled()
	})
})
