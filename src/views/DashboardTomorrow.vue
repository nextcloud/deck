<!--
  - SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<NcDashboardWidget
		:items="cards"
		emptyContentIcon="icon-deck"
		:emptyContentMessage="t('deck', 'No upcoming cards')"
		:showMoreText="t('deck', 'upcoming cards tomorrow')"
		:showMoreUrl="showMoreUrl"
		:loading="loading"
		@hide="() => {}"
		@markDone="() => {}">
		<template #default="{ item }">
			<Card :card="item" />
		</template>
	</NcDashboardWidget>
</template>

<script>
import { generateUrl } from '@nextcloud/router'
import { NcDashboardWidget } from '@nextcloud/vue'
import { mapActions, mapState } from 'pinia'
import Card from '../components/dashboard/Card.vue'
import { useDashboardStore } from '../stores/dashboard.js'

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
