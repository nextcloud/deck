/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { expect, jest, test } from '@jest/globals'
import { parseId } from './BoardElement.vue'

jest.mock('@nextcloud/l10n', () => ({ translate: (_, text) => text }), { virtual: true })
jest.mock('../logger.js', () => ({ error: jest.fn() }))

test.each([null, undefined, '', '0', '-1', '1.5', '1e2', ' 42', '9007199254740992'])('rejects invalid ID %s', (value) => {
	expect(() => parseId(value)).toThrow()
})

test('accepts positive safe integer IDs', () => {
	expect(parseId('42')).toBe(42)
	expect(parseId(String(Number.MAX_SAFE_INTEGER))).toBe(Number.MAX_SAFE_INTEGER)
})
