<!--
  - @copyright Copyright (c) 2018 Julius Härtl <jus@bitgrid.net>
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
  - MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
  - GNU Affero General Public License for more details.
  -
  - You should have received a copy of the GNU Affero General Public License
  - along with this program. If not, see <http://www.gnu.org/licenses/>.
  -
  -->

<template>
	<div>
		<Controls :dashboard-name="filter" />

		<div v-if="filter=='due'" class="dashboard">
			<div class="dashboard-column">
				<h2>overdue</h2>
				<div v-for="card in withDueDashboardGroup.overdue" :key="card.id">
					<!-- <CardItem :id="card.id" /> -->
					{{ card.title }}
				</div>
			</div>

			<div class="dashboard-column">
				<h2>today</h2>
				{{ withDueDashboardGroup.today }}
			</div>

			<div class="dashboard-column">
				<h2>tomorrow</h2>
				{{ withDueDashboardGroup.tomorrow }}
			</div>

			<div class="dashboard-column">
				<h2>this week</h2>
				{{ withDueDashboardGroup.thisWeek }}
			</div>
		</div>

		<div v-if="filter=='assigned'" class="dashboard">
			<div class="dashboard-column">
				<h2>overdue</h2>
				<div v-for="card in assignedCardsDashboardGroup.overdue" :key="card.id">
					{{ card.title }}
				</div>
			</div>

			<div class="dashboard-column">
				<h2>today</h2>
				{{ assignedCardsDashboardGroup.today }}
			</div>

			<div class="dashboard-column">
				<h2>tomorrow</h2>
				{{ assignedCardsDashboardGroup.tomorrow }}
			</div>

			<div class="dashboard-column">
				<h2>this week</h2>
				{{ assignedCardsDashboardGroup.thisWeek }}
			</div>
		</div>

	</div>
</template>

<script>

import Controls from '../Controls'
import CardItem from '../cards/CardItem'
import { mapGetters } from 'vuex'

export default {
	name: 'Dashboards',
	components: {
		Controls,
		CardItem,
	},
	props: {
		filter: {
			type: String,
			default: '',
		},
	},
	computed: {
		...mapGetters([
			'withDueDashboard',
			'assignedCardsDashboard',
		]),
		withDueDashboardGroup() {
			return this.groupByDue(this.withDueDashboard)
		},
		assignedCardsDashboardGroup() {
			return this.groupByDue(this.assignedCardsDashboard)
		},
	},
	methods: {
		groupByDue(dataset) {
			const all = {
				overdue: [],
				today: [],
				tomorrow: [],
				thisWeek: [],
			}
			dataset.forEach(card => {
				const days = Math.floor(moment(card.duedate).diff(this.$root.time, 'seconds') / 60 / 60 / 24)
				if (days < 0) {
				  all.overdue.push(card)
				}
				if (days === 0) {
					all.thisWeek.push(card)
				}
				if (days === 1) {
					all.tomorrow.push(card)
				}
				if (days === 2) {
					all.today.push(card)
				}

			})
			return all

		}
	},
	created() {
		this.$store.dispatch('loadDashboards')
	},

}
</script>

<style lang="scss" scoped>

.dashboard {
  display: flex;
	align-items: stretch;

  .dashboard-column {
    display: flex;
    flex-direction: column;
  }
}

</style>
