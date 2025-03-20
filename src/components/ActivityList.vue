<!--
  - SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<div class="activity-list">
		<div v-if="isLoading" class="icon icon-loading" />
		<ActivityEntry v-for="activity in activities"
			:key="activity.activity_id"
			:activity="activity" />
		<InfiniteLoading :identifier="objectId" @infinite="infiniteHandler" @change="changeObject">
			<template #spinner>
				<div class="icon-loading" />
			</template>
			<template #no-more>
				<div />
			</template>
			<template #no-results>
				<div />
			</template>
		</InfiniteLoading>
	</div>
</template>

<script>
import axios from '@nextcloud/axios'
import { generateOcsUrl } from '@nextcloud/router'
import ActivityEntry from './ActivityEntry.vue'
// import InfiniteLoading from 'v3-infinite-loading'

const ACTIVITY_FETCH_LIMIT = 50

export default {
	name: 'ActivityList',
	components: {
		ActivityEntry,
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

			const response = await axios.get(
				generateOcsUrl(`apps/activity/api/v2/activity/${this.filter}`) + '?' + params,
				{
					validateStatus: (status) => {
						return (status >= 200 && status < 300) || status === 304
					},
				},
			)

			if (response.status === 304) {
				this.endReached = true
				return []
			}

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
			if (activities.length === 0) {
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
