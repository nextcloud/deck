<template>
	<div>
		<div id="userDiv">
			<avatar :user="card.owner.uid" />
			<span class="has-tooltip username">
				{{ card.owner.displayname }}
			</span>
		</div>

		<div id="commentForm">
			<form @submit.prevent="">
				<input :placeholder="t('deck', 'New comment') + ' ...'" v-model="comment" type="text" autofocus>
				<input v-tooltip="t('deck', 'Save')" class="icon-confirm" type="submit"
					value="">
			</form>
		</div>

		<div id="commentsFeed">

		</div>
	</div>
</template>

<script>
import { mapState } from 'vuex'
import { Avatar } from 'nextcloud-vue'
export default {
	name: 'CommentsTabSidebar',
	components: {
		Avatar
	},
	props: {
		card: {
			type: Object,
			default: undefined
		}
	},
	data() {
		return {
			comment: ''
			
		}
	},
	computed: {
		...mapState({
			comments: state => state.comments.comments
		})

	},
	created() {
		this.loadComments()
	},
	
	methods: {
		loadComments() {
			this.$store.dispatch('listComments', this.card)
		}
	}
}
</script>

<style lang="scss">
	#userDiv {
		margin-bottom: 20px;
	}
	.username {
		padding: 12px 9px;
		flex-grow: 1;
	}
	
</style>
