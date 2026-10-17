/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { registerBoardElement } from './embedding/BoardElement.js'

import './shared-init.js'

registerBoardElement(async (...args) => {
	const { mountBoard } = await import('./embedding/mountBoard.js')
	return mountBoard(...args)
})
