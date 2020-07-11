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
	<div>
		<a :href="cardLink(card)" class="card" v-for="card in assignedCardsDashboard">
			<div class="card--header">
				<DueDate class="right" :card="card" />
				<span>{{ card.title }}</span>
			</div>
			<ul v-if="card.labels && card.labels.length"
				  class="labels"
				  @click="openCard">
				<li v-for="label in card.labels" :key="label.id" :style="labelStyle(label)">
					<span>{{ label.title }}</span>
				</li>
			</ul>
		</a>
	</div>
</template>

<script>
import { mapGetters } from 'vuex'
import labelStyle from './../mixins/labelStyle'
import DueDate from '../components/cards/badges/DueDate'
import { generateUrl } from '@nextcloud/router'

export default {
	name: 'Dashboard',
	components: {
		DueDate,
	},
	mixins: [ labelStyle ],
	beforeMount() {
		this.$store.dispatch('loadDashboards')
	},
	computed: {
		...mapGetters([
			'withDueDashboard',
			'assignedCardsDashboard',
		]),
		cards() {
			return [
				...this.withDueDashboard,
				...this.assignedCardsDashboard,
			]
		},
		cardLink() {
			return (card) => {
				return generateUrl('/apps/deck') + `#/board/${card.boardId}/card/${card.id}`
			}
		},
	},
}
</script>

<style lang="scss" scoped>
	@import './../css/labels';

	.card {
		display: block;
		border-radius: var(--border-radius);
		margin-bottom: 8px;
		padding: 5px;
		border: 1px solid var(--color-border);

		&:hover {
			background-color: var(--color-background-hover);
		}
	}
	.card--header {
		overflow: hidden;
		margin-bottom: 5px;
		span {
			display: inline-block;
			padding: 5px;
		}
	}
	.labels {
		margin-left: 0;
	}
	.duedate::v-deep {
		.due {
			margin: 0;
		}
	}
	.right {
		float: right;
	}
</style>
