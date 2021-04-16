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
	<div class="activity-list">
		<div v-if="isLoading" class="icon icon-loading" />
		<ActivityEntry v-for="activity in activities"
			:key="activity.activity_id"
			:activity="activity" />
		<InfiniteLoading :identifier="objectId" @infinite="infiniteHandler" @change="changeObject">
			<div slot="spinner" class="icon-loading" />
			<div slot="no-more" />
			<div slot="no-results" />
		</InfiniteLoading>
	</div>
</template>

<script>
import axios from '@nextcloud/axios'
import { generateOcsUrl } from '@nextcloud/router'
import ActivityEntry from './ActivityEntry'
import InfiniteLoading from 'vue-infinite-loading'

const ACTIVITY_FETCH_LIMIT = 50

export default {
	name: 'ActivityList',
	components: {
		ActivityEntry,
		InfiniteLoading,
	},
	props: {
		filter: {
			type: String,
			default: 'deck',
		},
		type: {
			type: String,
			required: true,
		},
		objectId: {
			type: Number,
			required: true,
		},
		objectType: {
			type: String,
			required: true,
		},
	},
	data() {
		return {
			activities: [],
			isLoading: false,
			since: 0,
			endReached: false,
		}
	},
	methods: {
		async loadActivity() {
			const params = new URLSearchParams()
			params.append('format', 'json')
			params.append('type', this.type)
			params.append('since', this.since)
			params.append('object_type', this.objectType)
			params.append('object_id', '' + this.objectId)
			params.append('limit', ACTIVITY_FETCH_LIMIT)

			const response = await axios.get(generateOcsUrl(`apps/activity/api/v2/activity/${this.filter}`) + '?' + params)
			let activities = response.data.ocs.data
			if (this.filter === 'deck') {
				// We need to manually filter activities here, since currently we use two different types and there is no way
				// to tell the backend to fetch all activites related to cards of a given board
				activities = activities.filter((activity) => {
					return (activity.object_type === 'deck_board' && activity.object_id === this.objectId)
							|| (activity.object_type === 'deck_card' && activity.subject_rich[1].board.id === this.objectId)
				})
			}
			this.activities.push(...activities)
			if (response.data.ocs.meta.statuscode === 304 || activities.length === 0) {
				this.endReached = true
				return []
			}
			this.since = (activities[activities.length - 1].activity_id)
			return activities
		},
		async infiniteHandler($state) {
			await this.loadActivity()
			if (!this.endReached) {
				$state.loaded()
			} else {
				$state.complete()
			}
		},
		changeObject() {
			this.since = 0
			this.activities = []
			this.endReached = false
		},
	},
}
</script>

<style scoped>
	.activity-list {
		margin-bottom: 100px;
	}
</style>
