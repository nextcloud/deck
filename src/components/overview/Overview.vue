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
	<div class="overview-wrapper">
		<Controls :overview-name="filterDisplayName" />

		<div v-if="loading" key="loading" class="emptycontent">
			<div class="icon icon-loading" />
			<h2>{{ t('deck', 'Loading filtered view') }}</h2>
			<p />
		</div>

		<div v-else-if="isValidFilter" class="overview">
			<div class="dashboard-column">
				<h3>{{ t('deck', 'Overdue') }}</h3>
				<div v-for="card in assignedCardsDashboard.overdue" :key="card.id">
					<CardItem :id="card.id" />
				</div>
			</div>

			<div class="dashboard-column">
				<h3>{{ t('deck', 'Today') }}</h3>
				<div v-for="card in assignedCardsDashboard.today" :key="card.id">
					<CardItem :id="card.id" />
				</div>
			</div>

			<div class="dashboard-column">
				<h3>{{ t('deck', 'Tomorrow') }}</h3>
				<div v-for="card in assignedCardsDashboard.tomorrow" :key="card.id">
					<CardItem :id="card.id" />
				</div>
			</div>

			<div class="dashboard-column">
				<h3>{{ t('deck', 'Next 7 days') }}</h3>
				<div v-for="card in assignedCardsDashboard.nextSevenDays" :key="card.id">
					<CardItem :id="card.id" />
				</div>
			</div>

			<div class="dashboard-column">
				<h3>{{ t('deck', 'Later') }}</h3>
				<div v-for="card in assignedCardsDashboard.later" :key="card.id">
					<CardItem :id="card.id" />
				</div>
			</div>

			<div class="dashboard-column">
				<h3>{{ t('deck', 'No due') }}</h3>
				<div v-for="card in assignedCardsDashboard.nodue" :key="card.id">
					<CardItem :id="card.id" />
				</div>
			</div>
		</div>

		<GlobalSearchResults />
	</div>
</template>

<script>

import Controls from '../Controls.vue'
import CardItem from '../cards/CardItem.vue'
import { mapGetters } from 'vuex'
import GlobalSearchResults from '../search/GlobalSearchResults.vue'

const FILTER_UPCOMING = 'upcoming'

const SUPPORTED_FILTERS = [
	FILTER_UPCOMING,
]

export default {
	name: 'Overview',
	components: {
		GlobalSearchResults,
		Controls,
		CardItem,
	},
	props: {
		filter: {
			type: String,
			default: FILTER_UPCOMING,
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
			case FILTER_UPCOMING:
				return t('deck', 'Upcoming cards')
			default:
				return ''
			}
		},
		...mapGetters([
			'assignedCardsDashboard',
		]),
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
				if (this.filter === FILTER_UPCOMING) {
					await this.$store.dispatch('loadUpcoming')
				}
			} catch (e) {
				console.error(e)
			}
			this.loading = false
		},
	},

}
</script>

<style lang="scss" scoped>
@import './../../css/variables';

.overview-wrapper {
	position: relative;
	width: 100%;
	height: 100%;
	max-height: calc(100vh - 50px);
	display: flex;
	flex-direction: column;
}

.overview {
	position: relative;
	height: calc(100% - 44px);
	overflow-x: scroll;
	display: flex;
	align-items: stretch;
	padding-left: $board-spacing;
	padding-right: $board-spacing;

	.dashboard-column {
		display: flex;
		flex-direction: column;
		width: $stack-width;
		margin-left: $stack-spacing;
		margin-right: $stack-spacing;

		h3 {
			margin: -6px;
			margin-bottom: 12px;
			padding: 6px 13px;
			position: sticky;
			top: 0;
			z-index: 100;
			background-color: var(--color-main-background);
			border: 1px solid var(--color-main-background);
		}
	}
}

</style>
