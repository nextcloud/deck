/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { describe, expect, it, jest } from '@jest/globals'
import { useFormatRelativeTime } from '@nextcloud/vue'
import relativeDate from './relativeDate.js'

jest.mock('@nextcloud/vue', () => ({ useFormatRelativeTime: jest.fn() }), { virtual: true })

describe('relativeDate mixin', () => {
	it('does not count up seconds', () => {
		relativeDate.computed.relativeDate()(1000)
		expect(useFormatRelativeTime).toHaveBeenCalledWith(1000, { ignoreSeconds: true, relativeTime: 'short' })
	})
})
