<template>
	<div>
		<div class="comment--header">
			<NcAvatar :user="currentUser.uid" />
			<span class="has-tooltip username">
				{{ currentUser.displayName }}
			</span>
		</div>

		<CommentItem v-if="replyTo"
			:comment="replyTo"
			:reply="true"
			:preview="true"
			@cancel="cancelReply" />
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
			<div :class="{ 'icon-comment': !error, 'icon-error': error }" />
			<p>{{ error || t('deck', 'No comments yet. Begin the discussion!') }}</p>
		</div>
	</div>
</template>

<script>
import { mapState, mapGetters } from 'vuex'
import { NcAvatar } from '@nextcloud/vue'
import CommentItem from './CommentItem'
import CommentForm from './CommentForm'
import InfiniteLoading from 'vue-infinite-loading'
import { getCurrentUser } from '@nextcloud/auth'

export default {
	name: 'CardSidebarTabComments',
	components: {
		NcAvatar,
		CommentItem,
		CommentForm,
		InfiniteLoading,
	},
	props: {
		card: {
			type: Object,
			default: undefined,
		},
		tabQuery: {
			type: String,
			required: false,
			default: null,
		},
	},
	data() {
		return {
			newComment: '',
			isLoading: false,
			currentUser: getCurrentUser(),
			error: null,
		}
	},
	computed: {
		...mapState({
			currentBoard: state => state.currentBoard,
			replyTo: state => state.comment.replyTo,
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
		card: {
			immediate: true,
			handler() {
				this.loadComments()
			},
		},
	},
	methods: {
		async infiniteHandler($state) {
			this.error = null
			try {
				await this.loadMore()
				if (this.hasMoreComments(this.card.id)) {
					$state.loaded()
				} else {
					$state.complete()
				}
			} catch (e) {
				console.error('Failed to fetch more comments during infinite loading', e)
				this.error = t('deck', 'Failed to load comments')
				$state.complete()
			}
		},
		async loadComments() {
			this.$store.dispatch('setReplyTo', null)
			this.error = null
			this.isLoading = true
			try {
				await this.$store.dispatch('fetchComments', { cardId: this.card.id })
				this.isLoading = false
				if (this.card.commentsUnread > 0) {
					await this.$store.dispatch('markCommentsAsRead', this.card.id)
				}
			} catch (e) {
				this.isLoading = false
				console.error('Failed to fetch more comments during infinite loading', e)
				this.error = t('deck', 'Failed to load comments')
			}
		},
		async createComment(content) {
			const commentObj = {
				cardId: this.card.id,
				comment: content,
			}
			await this.$store.dispatch('createComment', commentObj)
			this.$store.dispatch('setReplyTo', null)
			this.newComment = ''
			await this.loadComments()
		},
		async loadMore() {
			this.isLoading = true
			await this.$store.dispatch('fetchMore', { cardId: this.card.id })
			this.isLoading = false
		},
		cancelReply() {
			this.$store.dispatch('setReplyTo', null)
		},
	},
}
</script>

<style scoped lang="scss">
	@import '../../css/comments';
</style>
