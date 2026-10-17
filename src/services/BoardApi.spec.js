/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import axios from '@nextcloud/axios'
import { BoardApi } from './BoardApi.js'

jest.mock('@nextcloud/axios', () => ({
	post: jest.fn(),
}))

jest.mock('@nextcloud/router', () => ({
	generateOcsUrl: jest.fn(url => url),
	generateUrl: jest.fn(url => url),
}))

describe('BoardApi', () => {
	describe('cloneBoard', () => {
		it('returns the cloned board on success', async () => {
			const board = { id: 42 }
			const clonedBoard = { id: 84, title: 'Cloned board' }
			axios.post.mockResolvedValue({ data: clonedBoard })

			await expect(new BoardApi().cloneBoard(board)).resolves.toEqual(clonedBoard)
		})

		it('rejects when the request fails', async () => {
			const error = new Error('Clone failed')
			axios.post.mockRejectedValue(error)

			await expect(new BoardApi().cloneBoard({ id: 42 })).rejects.toBe(error)
		})
	})
})
