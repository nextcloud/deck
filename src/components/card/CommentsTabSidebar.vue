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
				<input :placeholder="t('deck', 'New comment') + ' ...'" v-model="newComment" type="text"
					autofocus required>
				<input v-tooltip="t('deck', 'Save')" class="icon-confirm" type="submit"
					value="">
			</form>
		</div>

		<div v-if="isLoading" class="icon icon-loading" />

		<ul id="commentsFeed">
			<CommentItem v-for="comment in comments[card.id]" :comment="comment" :key="comment.id"
				@doReload="loadComments" />
		</ul>
		<button @click="loadMore">Load More</button>
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
			newComment: '',
			isLoading: false,
			limit: 20,
			offset: 0
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
			this.isLoading = true
			this.card.limit = this.limit
			this.card.offset = this.offset
			this.$store.dispatch('listComments', this.card).then(response => {
				this.isLoading = false
			})
		},
		createComment() {
			let commentObj = {
				cardId: this.card.id,
				comment: this.newComment
			}
			this.$store.dispatch('createComment', commentObj)
			this.loadComments()
			this.newComment = ''
		},
		loadMore() {
			this.offset = this.offset + this.limit
			this.loadComments()
		}
	}
}
</script>

<style lang="scss">
	#commentForm form {
		display: flex
	}
	#commentForm form input {
		flex-grow: 1;
	}
	#userDiv {
		margin-bottom: 20px;
	}
	.username {
		padding: 12px 9px;
		flex-grow: 1;
	}
</style>
