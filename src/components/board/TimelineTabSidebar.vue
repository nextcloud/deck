<template>
	<div>
		<div v-if="isLoading" class="icon icon-loading" />

		<ActivityEntry v-for="entry in boardActivity" v-else :key="entry.activity_id"
			:activity="entry"
		/>
		<button v-if="activityLoadMore" @click="loadMore">
			Load More
		</button>
	</div>
</template>

<script>
import { mapState } from 'vuex'
import ActivityEntry from '../ActivityEntry'

export default {
	name: 'TimelineTabSidebar',
	components: {
		ActivityEntry
	},
	props: {
		board: {
			type: Object,
			default: undefined
		}
	},
	data() {
		return {
			isLoading: false,
			params: {
				type: 'deck',
				since: 0,
				object_id: this.board.id
			}
		}
	},
	computed: {
		...mapState({
			boardActivity: 'activity',
			activityLoadMore: 'activityLoadMore'
		})
	},
	created() {
		this.loadBoardActivity()
	},
	methods: {
		loadBoardActivity() {
			this.isLoading = true
			this.$store.dispatch('loadActivity', this.params).then(response => {
				this.isLoading = false
			})
		},
		loadMore() {
			let array = Object.values(this.boardActivity)
			let aId = (array[array.length - 1].activity_id)

			this.params.since = aId
			this.loadBoardActivity()
		}
	}
}
</script>
