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
		<div v-if="isLoading" class="icon icon-loading" />
		<div v-else>
			<ActivityEntry v-for="entry in cardActivity"

				:key="entry.activity_id"
				:activity="entry" />
		</div>
		<button v-if="activityLoadMore" @click="loadMore">
			Load More
		</button>
	</div>
</template>

<script>
import ActivityEntry from '../ActivityEntry'

import { mapState } from 'vuex'

export default {
	name: 'CardSidebarTabActivity',
	components: {
		ActivityEntry,
	},
	props: {
		card: {
			type: Object,
			default: null,
		},
	},
	data() {
		return {
			id: 'activity',
			isLoading: false,
			params: {
				type: 'filter',
				since: 0,
				object_type: 'deck_card',
				object_id: this.id,
			},
		}
	},
	computed: {
		...mapState({
			currentBoard: state => state.currentBoard,
			assignableUsers: state => state.assignableUsers,
			cardActivity: 'activity',
			activityLoadMore: 'activityLoadMore',
		}),
	},
	mounted() {
		this.params.object_id = this.card.id
		this.loadCardActivity()
	},
	methods: {
		loadCardActivity() {
			this.isLoading = true
			this.$store.dispatch('loadActivity', this.params).then(response => {
				this.isLoading = false
			})
		},
		loadMore() {
			const array = Object.values(this.cardActivity)
			const aId = (array[array.length - 1].activity_id)

			this.params.since = aId
			this.loadCardActivity()
		},
	},
}
</script>

<style scoped>

</style>
