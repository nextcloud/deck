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
		<Controls :dashboard-name="filterDisplayName" />

		<div v-if="loading" key="loading" class="emptycontent">
			<div class="icon icon-loading" />
			<h2>{{ t('deck', 'Loading filtered view') }}</h2>
			<p />
		</div>

		<div v-else>
			<div v-if="isValidFilter" class="dashboard">
				<div class="dashboard-column">
					<h2>{{ t('deck', 'No due') }}</h2>
					<div v-for="card in cardsByDueDate.nodue" :key="card.id">
						<CardItem :id="card.id" />
					</div>
				</div>

				<div class="dashboard-column">
					<h2>{{ t('deck', 'Overdue') }}</h2>
					<div v-for="card in cardsByDueDate.overdue" :key="card.id">
						<CardItem :id="card.id" />
					</div>
				</div>

				<div class="dashboard-column">
					<h2>{{ t('deck', 'Today') }}</h2>
					<div v-for="card in cardsByDueDate.today" :key="card.id">
						<CardItem :id="card.id" />
					</div>
				</div>

				<div class="dashboard-column">
					<h2>{{ t('deck', 'Tomorrow') }}</h2>
					<div v-for="card in cardsByDueDate.tomorrow" :key="card.id">
						<CardItem :id="card.id" />
					</div>
				</div>

				<div class="dashboard-column">
					<h2>{{ t('deck', 'This week') }}</h2>
					<div v-for="card in cardsByDueDate.thisWeek" :key="card.id">
						<CardItem :id="card.id" />
					</div>
				</div>

				<div class="dashboard-column">
					<h2>{{ t('deck', 'Later') }}</h2>
					<div v-for="card in cardsByDueDate.later" :key="card.id">
						<CardItem :id="card.id" />
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

const FILTER_DUE = 'due'
const FILTER_ASSIGNED = 'assigned'

const SUPPORTED_FILTERS = [
	FILTER_ASSIGNED,
	FILTER_DUE,
]

export default {
	name: 'Overview',
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
	data() {
		return {
			loading: true,
		}
	},
	computed: {
		isValidFilter() {
			return SUPPORTED_FILTERS.indexOf(this.filter) > -1
		},
		filterDisplayName() {
			switch (this.filter) {
			case 'assigned':
				return t('deck', 'My assigned cards')
			default:
				return ''
			}
		},
		...mapGetters([
			'withDueDashboard',
			'assignedCardsDashboard',
		]),
		cardsByDueDate() {
			switch (this.filter) {
			case FILTER_ASSIGNED:
				return this.groupByDue(this.assignedCardsDashboard)
			case FILTER_DUE:
				return this.groupByDue(this.withDueDashboard)
			default:
				return null
			}
		},
	},
	watch: {
		'$route.params.filter'() {
			this.getData()
		},
	},
	created() {
		this.getData()
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
					const hours = Math.floor(moment(card.duedate).diff(this.$root.time, 'seconds') / 60 / 60)
					const d = new Date()
					const currentHour = d.getHours()
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
