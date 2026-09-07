<!--
  - SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<NcDashboardWidget :items="cards"
		empty-content-icon="icon-deck"
		:empty-content-message="t('deck', 'No upcoming cards')"
		:show-more-text="t('deck', 'upcoming cards tomorrow')"
		:show-more-url="showMoreUrl"
		:loading="loading"
		@hide="() => {}"
		@markDone="() => {}">
		<template #default="{ item }">
			<Card :card="item" />
		</template>
	</NcDashboardWidget>
</template>

<script>
import { NcDashboardWidget } from '@nextcloud/vue'
import Card from '../components/dashboard/Card.vue'
import { generateUrl } from '@nextcloud/router'
import { useDashboardStore } from '../stores/dashboard.js'
import { mapActions, mapState } from 'pinia'

export default {
	name: 'DashboardTomorrow',
	components: {
		NcDashboardWidget,
		Card,
	},
	data() {
		return {
			loading: false,
		}
	},
	computed: {
		...mapState(useDashboardStore, ['assignedCards']),
		cards() {
			const list = [...this.assignedCards.tomorrow || []]
			list.sort((a, b) => {
				return (new Date(a.duedate)).getTime() - (new Date(b.duedate)).getTime()
			})
			return list
		},
		showMoreUrl() {
			return this.cards.length > 7 ? generateUrl('/apps/deck') : null
		},
	},
	beforeMount() {
		this.loading = true
		this.loadUpcoming().then(() => {
			this.loading = false
		})
	},
	methods: {
		...mapActions(useDashboardStore, ['loadUpcoming']),
	},
}
</script>

<style lang="scss" scoped>
	#deck-widget-empty-content {
		text-align: center;
		margin-top: 5vh;
	}
</style>
