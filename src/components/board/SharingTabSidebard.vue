<template>
		<div>
			<multiselect :options="sharees" @search-change="asyncFind" label="label">
				<template #option="scope">
					{{ scope.option.label }}
				</template>
			</multiselect>

			<ul
				id="shareWithList"
				class="shareWithList"
			>
				<li>
					<avatar :user="board.owner.uid" />
					<span class="has-tooltip username">
						{{ board.owner.displayname }}
					</span>
				</li>
				<li v-for="acl in board.acl" :key="acl.participant.uid">
					<avatar :user="acl.participant.uid" />
					<span class="has-tooltip username">
						{{ acl.participant.displayname }}
					</span>
				</li>
			</ul>
		</div>
</template>

<script>
import { Avatar, Multiselect } from 'nextcloud-vue'
import { mapGetters } from 'vuex'

export default {
	name: 'SharingTabSidebard',
	components: {
		Avatar,
		Multiselect,
	},
	data() {
		return {
			isLoading: false
		}
	},
	computed: {
		...mapGetters({
			sharees: 'sharees'
		})
	},
	props: {
		board: {
			type: Object,
			default: undefined
		}
	},
	methods: {
		asyncFind (query) {
			this.isLoading = true
			this.$store.dispatch('loadSharees').then(response => {
				this.isLoading = false
			})
		},
	}



}
</script>
