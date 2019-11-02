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
		<Controls />
		<div class="card-list">
			<div class="card-list-row card-list-header-row">
				<div class="card-list-bullet-cell" />
				<div class="card-list-bullet-cell"><div class="card-list-bullet" /></div>
				<div class="card-list-title-cell">Title</div>
				<div class="card-list-title-cell" />
				<div class="card-list-title-cell">Due</div>
				<div class="card-list-avatars-cell">Assigned</div>
				<div class="card-list-actions-cell" />
			</div>
			<CardItem v-for="card in filteredCards" :key="card.id" :card="card" />
		</div>
	</div>
</template>

<script>

import CardItem from './CardItem'
import Controls from './Controls'
import { mapGetters } from 'vuex'

export default {
	name: 'Main',
	components: {
		Controls,
		CardItem
	},
	props: {
		navFilter: {
			type: String,
			default: ''
		}
	},
	data: function() {
		return {
			loading: true
		}
	},
	methods: {
		fetchData() {
			this.filteredBoards.forEach(board => {
				this.$store.dispatch('loadStacks', board.id).then(response => {
					this.loading = false
				})
			})
		}
	},
	computed: {
		...mapGetters([
			'filteredBoards'

		]),
		allStacks() {
			return this.$store.getters.allStacks
		},
		allCards() {
			return this.$store.getters.allCards
		},
		filteredCards() {
			if (this.navFilter === 'due') {
				return this.allCards.filter((card) => {
					return card.duedate !== null
				})
			} else if (this.navFilter === 'my') {
				return this.allCards.filter((card) => {
					if (card.assignedUsers.length > 0) {
						return card.assignedUsers.filter((user) => {
							return user.id === 18
						})
					}
				})
			} else {
				return this.allCards
			}
		}
	},
	watch: {
		navFilter: function(value) {
			this.$store.commit('setCollectionFilter', value)
		},
		filteredBoards() {
			this.fetchData()
		}
	}
}
</script>

<style lang="scss">
	.card-list {

		.card-list-row {
			align-items: center;
			border-bottom: 1px solid #ededed;
			display: flex;
		}

		.card-list-header-row {
			color: var(--color-text-lighter);
		}

		.card-list-bullet-cell,
		.card-list-avatars-cell {
			padding: 6px 15px;
		}

		.card-list-avatars-cell {
			flex: 0 0 50px;
		}

		.card-list-avatar,
		.card-list-bullet {
			height: 32px;
			width: 32px;
		}

		.card-list-title-cell {
			flex: 1 0 auto;
			padding: 15px;
		}

		.card-list-actions-cell {
			// placeholder
			flex: 0 0 50px;
		}
	}
</style>
