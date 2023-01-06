<!--
  - @copyright Copyright (c) 2020 Julius Härtl <jus@bitgrid.net>
  -
  - @author Julius Härtl <jus@bitgrid.net>
  -
  - @license GNU AGPL version 3 or any later version
  -
  - This program is free software: you can redistribute it and/or modify
  - it under the terms of the GNU Affero General Public License as
  - published by the Free Software Foundation, either version 3 of the
  - License, or (at your option) any later version.
  -
  - This program is distributed in the hope that it will be useful,
  - but WITHOUT ANY WARRANTY; without even the implied warranty of
  - MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
  - GNU Affero General Public License for more details.
  -
  - You should have received a copy of the GNU Affero General Public License
  - along with this program. If not, see <http://www.gnu.org/licenses/>.
  -
  -->

<template>
	<DashboardWidget :items="cards"
		empty-content-icon="icon-deck"
		:empty-content-message="t('deck', 'No upcoming cards')"
		:show-more-text="t('deck', 'upcoming cards today')"
		:show-more-url="showMoreUrl"
		:loading="loading"
		@hide="() => {}"
		@markDone="() => {}">
		<template #default="{ item }">
			<Card :card="item" />
		</template>
	</DashboardWidget>
</template>

<script>
import { DashboardWidget } from '@nextcloud/vue-dashboard'
import { mapGetters } from 'vuex'
import Card from '../components/dashboard/Card.vue'
import { generateUrl } from '@nextcloud/router'

export default {
	name: 'DashboardToday',
	components: {
		DashboardWidget,
		Card,
	},
	data() {
		return {
			loading: false,
		}
	},
	computed: {
		...mapGetters([
			'assignedCardsDashboard',
		]),
		cards() {
			const today = new Date()
			const list = [
				...this.assignedCardsDashboard,
			].filter((card) => {
				return card.duedate !== null
			}).filter((card) => {
				return (new Date(card.duedate)).getDate() === (new Date(today)).getDate()
			})
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
		this.$store.dispatch('loadUpcoming').then(() => {
			this.loading = false
		})
	},
}
</script>

<style lang="scss" scoped>
	#deck-widget-empty-content {
		text-align: center;
		margin-top: 5vh;
	}
</style>
