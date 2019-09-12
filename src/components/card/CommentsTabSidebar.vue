<template>
	<div>
		<div id="userDiv">
			<avatar :user="card.owner.uid" />
			<span class="has-tooltip username">
				{{ card.owner.displayname }}
			</span>
		</div>

		<div id="commentForm">
			<form @submit.prevent="createComment()">
				<input :placeholder="t('deck', 'New comment') + ' ...'" v-model="comment" type="text"
					autofocus required>
				<input v-tooltip="t('deck', 'Save')" class="icon-confirm" type="submit"
					value="">
			</form>
		</div>

		<ul id="commentsFeed">
			<CommentItem v-for="comment in comments" :comment="comment" :key="comment.id"
				@doReload="loadComments" />
		</ul>
	</div>
</template>

<script>
import { mapState } from 'vuex'
import { Avatar } from 'nextcloud-vue'
import { Actions } from 'nextcloud-vue/dist/Components/Actions'
import { ActionButton } from 'nextcloud-vue/dist/Components/ActionButton'
import CommentItem from './CommentItem'

export default {
	name: 'CommentsTabSidebar',
	components: {
		Avatar,
		Actions,
		ActionButton,
		CommentItem
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
			comments: state => state.comment.comments
		})

	},
	watch: {
		'card': {
			handler() {
				this.loadComments()
			}
		}
	},
	created() {
		this.loadComments()
	},

	methods: {
		loadComments() {
			this.$store.dispatch('listComments', this.card)
		},
		createComment() {
			this.card.comment = this.comment
			this.$store.dispatch('createComment', this.card)
			this.loadComments()
			this.card.comment = ''
			this.comment = ''
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
