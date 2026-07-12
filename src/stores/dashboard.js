/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { defineStore } from 'pinia'
import { OverviewApi } from '../services/OverviewApi.js'

const apiClient = new OverviewApi()

export const useDashboardStore = defineStore('dashboard', {
	state: () => ({
		assignedCards: [],
	}),
	actions: {
		async loadUpcoming(hideNoDueOnOverview) {
			const upcommingCards = await apiClient.get('upcoming', hideNoDueOnOverview)
			this.assignedCards = upcommingCards
		},
	},
})
