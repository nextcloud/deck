/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { describe, expect, it, jest } from '@jest/globals'
import GlobalSearchResults from './GlobalSearchResults.vue'

jest.mock('@nextcloud/axios', () => ({}), { virtual: true })
jest.mock('@nextcloud/router', () => ({}), { virtual: true })
jest.mock('@nextcloud/vue', () => ({}), { virtual: true })
jest.mock('@vueuse/components', () => ({}), { virtual: true })
jest.mock('pinia', () => ({ mapActions: () => ({}), mapState: () => ({}) }))
jest.mock('../cards/CardItem.vue', () => ({}))
jest.mock('./Placeholder.vue', () => ({}))
jest.mock('../../logger.js', () => ({ __esModule: true, default: { error: jest.fn() } }))
jest.mock('../../stores/card.js', () => ({}))
jest.mock('../../stores/settings.js', () => ({}))

const { setSentinel, canLoadMore } = GlobalSearchResults.methods

function context() {
	return { hasMore: true, sentinel: null }
}

describe('GlobalSearchResults infinite loading', () => {
	it('loads more for the mounted sentinel', () => {
		const ctx = context()
		const sentinel = {}
		setSentinel.call(ctx, sentinel)
		expect(canLoadMore.call(ctx, sentinel)).toBe(true)
	})

	it('stops once there are no more results', () => {
		const ctx = context()
		const sentinel = {}
		setSentinel.call(ctx, sentinel)
		ctx.hasMore = false
		expect(canLoadMore.call(ctx, sentinel)).toBe(false)
	})

	it('does not load more for a sentinel that was unmounted', () => {
		const ctx = context()
		const sentinel = {}
		setSentinel.call(ctx, sentinel)
		setSentinel.call(ctx, null)
		expect(canLoadMore.call(ctx, sentinel)).toBe(false)
	})

	it('only loads more for the sentinel that replaced an unmounted one', () => {
		const ctx = context()
		const unmounted = {}
		const mounted = {}
		setSentinel.call(ctx, unmounted)
		setSentinel.call(ctx, null)
		setSentinel.call(ctx, mounted)
		expect(canLoadMore.call(ctx, unmounted)).toBe(false)
		expect(canLoadMore.call(ctx, mounted)).toBe(true)
	})
})
