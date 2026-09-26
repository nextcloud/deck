/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { formatCsvList } from './BoardApi.js'

jest.mock('@nextcloud/axios', () => ({}))

jest.mock('@nextcloud/router', () => ({
	generateOcsUrl: jest.fn(url => url),
	generateUrl: jest.fn(url => url),
}))

describe('formatCsvList', () => {
	it('joins values without a trailing separator', () => {
		const labels = [{ title: 'Backend' }, { title: 'Urgent' }]

		expect(formatCsvList(labels, label => label.title)).toBe('Backend, Urgent')
	})

	it('escapes double quotes in values', () => {
		const assignments = [{ participant: { displayname: 'Jane "JJ" Doe' } }]

		expect(formatCsvList(assignments, assignment => assignment.participant.displayname))
			.toBe('Jane ""JJ"" Doe')
	})

	it('returns an empty string for an empty list', () => {
		expect(formatCsvList([], label => label.title)).toBe('')
	})
})
