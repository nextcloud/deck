/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { describe, expect, it, jest } from '@jest/globals'
import NcActionsCardMenuEntries from './NcActionsCardMenuEntries.vue'

jest.mock('@nextcloud/auth', () => ({}))
jest.mock('@nextcloud/dialogs', () => ({}), { virtual: true })
jest.mock('@nextcloud/event-bus', () => ({}))
jest.mock('@nextcloud/router', () => ({}), { virtual: true })
jest.mock('@nextcloud/vue', () => ({}), { virtual: true })
jest.mock('pinia', () => ({
	mapActions: () => ({}),
	mapState: () => ({}),
}))
jest.mock('@nextcloud/dialogs/style.css', () => ({}), { virtual: true })
jest.mock('../../stores/actions.js', () => ({}))
jest.mock('../../stores/board.js', () => ({}))
jest.mock('../../stores/card.js', () => ({}))
jest.mock('../../stores/stack.js', () => ({}))
jest.mock('../../stores/trashbin.js', () => ({}))
jest.mock('vue-material-design-icons/AccountMinusOutline.vue', () => ({}))
jest.mock('vue-material-design-icons/AccountPlusOutline.vue', () => ({}))
jest.mock('vue-material-design-icons/ArchiveOutline.vue', () => ({}))
jest.mock('vue-material-design-icons/CardBulletedOutline.vue', () => ({}))
jest.mock('vue-material-design-icons/Check.vue', () => ({}))
jest.mock('vue-material-design-icons/Circle.vue', () => ({}))
jest.mock('vue-material-design-icons/Delete.vue', () => ({}))
jest.mock('vue-material-design-icons/OpenInNew.vue', () => ({}))
jest.mock('vue-material-design-icons/PencilOutline.vue', () => ({}))

const { canEdit } = NcActionsCardMenuEntries.computed

describe('NcActionsCardMenuEntries', () => {
	it('allows editing a card on a board the user can edit', () => {
		expect(canEdit.call({ card: { archived: false }, canEditBoard: true })).toBe(true)
	})

	it('does not allow editing a card on a read-only board', () => {
		expect(canEdit.call({ card: { archived: false }, canEditBoard: false })).toBe(false)
	})

	it('does not allow editing an archived card', () => {
		expect(canEdit.call({ card: { archived: true }, canEditBoard: true })).toBe(false)
	})
})
