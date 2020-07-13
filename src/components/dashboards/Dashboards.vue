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

		<div v-if="loading" key="loading" class="emptycontent">
			<div class="icon icon-loading" />
			<h2>{{ t('deck', 'Loading Dashboard') }}</h2>
			<p />
		</div>

		<div v-else>

			<div v-if="filter=='due'" class="dashboard">
				<div class="dashboard-column">
					<h2>{{ t('deck', 'overdue') }}</h2>
					<div v-for="card in dueOverdue" :key="card.id">
						<CardItem :item="card" />
					</div>
				</div>

				<div class="dashboard-column">
					<h2>{{ t('deck', 'today') }}</h2>
					<div v-for="card in withDueDashboardGroup.today" :key="card.id">
						<CardItem :item="card" />
					</div>
				</div>

				<div class="dashboard-column">
					<h2>{{ t('deck', 'tomorrow') }}</h2>
					<div v-for="card in withDueDashboardGroup.tomorrow" :key="card.id">
						<CardItem :item="card" />
					</div>
				</div>

				<div class="dashboard-column">
					<h2>{{ t('deck', 'this week') }}</h2>
					<div v-for="card in withDueDashboardGroup.later" :key="card.id">
						<CardItem :item="card" />
					</div>
				</div>

				<div class="dashboard-column">
					<h2>{{ t('deck', 'later') }}</h2>
					<div v-for="card in withDueDashboardGroup.thisWeek" :key="card.id">
						<CardItem :item="card" />
					</div>
				</div>
			</div>

			<div v-if="filter=='assigned'" class="dashboard">
				<div class="dashboard-column">
					<h2>{{ t('deck', 'no due') }}</h2>
					<div v-for="card in assignedCardsDashboardGroup.nodue" :key="card.id">
						<CardItem :item="card" />
					</div>
				</div>

				<div class="dashboard-column">
					<h2>{{ t('deck', 'overdue') }}</h2>
					<div v-for="card in assignedCardsDashboardGroup.overdue" :key="card.id">
						<CardItem :item="card" />
					</div>
				</div>

				<div class="dashboard-column">
					<h2>{{ t('deck', 'today') }}</h2>
					<div v-for="card in assignedCardsDashboardGroup.today" :key="card.id">
						<CardItem :item="card" />
					</div>
				</div>

				<div class="dashboard-column">
					<h2>{{ t('deck', 'tomorrow') }}</h2>
					<div v-for="card in assignedCardsDashboardGroup.tomorrow" :key="card.id">
						<CardItem :item="card" />
					</div>
				</div>

				<div class="dashboard-column">
					<h2>{{ t('deck', 'this week') }}</h2>
					<div v-for="card in assignedCardsDashboardGroup.thisWeek" :key="card.id">
						<CardItem :item="card" />
					</div>
				</div>

				<div class="dashboard-column">
					<h2>{{ t('deck', 'later') }}</h2>
					<div v-for="card in withDueDashboardGroup.later" :key="card.id">
						<CardItem :item="card" />
					</div>
				</div>
			</div>
		</div>
	</div>
</template>

<script>

import Controls from '../Controls'
import CardItem from '../cards/CardItem'
import { mapGetters } from 'vuex'
import moment from '@nextcloud/moment'

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
	data: function() {
		return {
			loading: true,
		}
	},
	computed: {
		...mapGetters([
			'withDueDashboard',
			'assignedCardsDashboard',
			'dueOverdue'
		]),
		withDueDashboardGroup() {
			return this.groupByDue(this.withDueDashboard)
		},
		assignedCardsDashboardGroup() {
			return this.groupByDue(this.assignedCardsDashboard)
		},
	},
	created() {
		this.getData()
	},
	watch: {
		"$route.params.filter"() {
			this.getData()
		}
	},
	methods: {
		async getData() {
			this.loading = true
			try {
				if (this.filter === 'due') {
					await this.$store.dispatch('loadDueDashboard')
				}

				if (this.filter === 'assigned') {
					await this.$store.dispatch('loadAssignDashboard')
				}
			} catch (e) {
				console.error(e)
			}
			this.loading = false
		},


		groupByDue(dataset) {
			const all = {
				nodue: [],
				overdue: [],
				today: [],
				tomorrow: [],
				thisWeek: [],
				later: [],
			}
			dataset.forEach(card => {

				if (card.duedate === null) {
					all.nodue.push(card)
				} else {
					const hours = Math.floor(moment(card.duedate).diff(this.$root.time, 'seconds') / 60 / 60 )
					let d = new Date()
					let currentHour = d.getHours()
					console.log(card.title +' '+ hours )
					if (hours < 0) {
						all.overdue.push(card)
					}
					if (hours >= 0 && hours < (24 - currentHour)) {
						all.today.push(card)
					}
					if (hours >= (24 - currentHour) && hours < (48 - currentHour)) {
						all.tomorrow.push(card)
					}
					if (hours >= (48 - currentHour) && hours < (24 * 7)) {
						all.thisWeek.push(card)
					}
					if (hours >= (24 * 7)) {
						all.later.push(card)
					}
				}

			})
			return all

		},
	},

}
</script>

<style lang="scss" scoped>
@import './../../css/variables';

.dashboard {
	display: flex;
	align-items: stretch;
	margin: $board-spacing;

	.dashboard-column {
		display: flex;
		flex-direction: column;
		width: $stack-width;
		margin: $stack-spacing;
	}
}

</style>
