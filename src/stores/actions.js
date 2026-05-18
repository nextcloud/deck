/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { defineStore } from 'pinia'

export const useActionsStore = defineStore('actions',
	{
		state: () => ({
			actions: {
				card: [],
			},
		}),
		actions: {
			async addCardAction(action) {
				this.actions.card.push(action)
			},
		},
	})
