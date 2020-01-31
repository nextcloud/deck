<template>
	<div>
		<div class="comment--header">
			<Avatar :user="card.owner.uid" />
			<span class="has-tooltip username">
				{{ card.owner.displayname }}
			</span>
		</div>

		<CommentForm v-model="newComment" @submit="createComment" />

		<ul v-if="getCommentsForCard(card.id).length > 0" id="commentsFeed">
			<CommentItem v-for="comment in getCommentsForCard(card.id)"
				:key="comment.id"
				:comment="comment"
				@doReload="loadComments" />
			<InfiniteLoading :identifier="card.id" @infinite="infiniteHandler">
				<div slot="spinner" class="icon-loading" />
				<div slot="no-more" />
				<div slot="no-results" />
			</InfiniteLoading>
		</ul>
		<div v-else-if="isLoading" class="icon icon-loading" />
		<div v-else class="emptycontent">
			<div class="icon-comment" />
			<p>{{ t('deck', 'No comments yet. Begin the discussion!') }}</p>
		</div>
	</div>
</template>

<script>
import { mapState, mapGetters } from 'vuex'
import { Avatar } from '@nextcloud/vue'
import CommentItem from './CommentItem'
import CommentForm from './CommentForm'
import InfiniteLoading from 'vue-infinite-loading'

export default {
	name: 'CardSidebarTabComments',
	components: {
		Avatar,
		CommentItem,
		CommentForm,
		InfiniteLoading,
	},
	props: {
		card: {
			type: Object,
			default: undefined,
		},
	},
	data() {
		return {
			newComment: '',
			isLoading: false,
		}
	},
	computed: {
		...mapState({
			currentBoard: state => state.currentBoard,
		}),
		...mapGetters([
			'getCommentsForCard',
			'hasMoreComments',
		]),
		members() {
			return this.currentBoard.users
		},
	},
	watch: {
		'card': {
			immediate: true,
			handler() {
				this.loadComments()
			},
		},
	},
	methods: {
		async infiniteHandler($state) {
			await this.loadMore()
			if (this.hasMoreComments(this.card.id)) {
				$state.loaded()
			} else {
				$state.complete()
			}
		},
		async loadComments() {
			this.isLoading = true
			await this.$store.dispatch('fetchComments', { cardId: this.card.id })
			this.isLoading = false
		},
		async createComment(content) {
			const commentObj = {
				cardId: this.card.id,
				comment: content,
			}
			await this.$store.dispatch('createComment', commentObj)
			this.newComment = ''
			await this.loadComments()
		},
		async loadMore() {
			this.isLoading = true
			await this.$store.dispatch('fetchMore', { cardId: this.card.id })
			this.isLoading = false
		},
	},
}
</script>

<style scoped lang="scss">
	@import "../../css/comments";
</style>
