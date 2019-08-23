<template>
	<div>
		<div v-for="entry in boardActivity" :key="entry.activity_id">
			<img :src="entry.icon">
			{{ entry.subject }}
			{{ getTime(entry.datetime) }}
		</div>
		<button @click="loadMore">Load More</button>
	</div>
</template>

<script>
import { mapGetters } from 'vuex'

export default {
	name: 'TimelineTabSidebard',
	components: {

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
		...mapGetters({
			boardActivity: 'activity'
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
		getTime(timestamp) {
			return OC.Util.relativeModifiedDate(timestamp)
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
