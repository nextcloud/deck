<!--
  - SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<div>
		<div class="comment--header">
			<NcAvatar :user="currentUser.uid" />
			<span class="username">
				{{ currentUser.displayName }}
			</span>
		</div>

		<CommentItem v-if="commentStore.replyTo"
			:comment="commentStore.replyTo"
			:reply="true"
			:preview="true"
			@cancel="cancelReply" />
		<CommentForm v-model="newComment" @submit="createComment" />

		<ul v-if="commentStore.getCommentsForCard(card.id).length > 0" id="commentsFeed">
			<div v-v-infinite-scroll="[infiniteHandler, {canLoadMore: () => commentStore.hasMoreComments(card.id)}]">
				<CommentItem v-for="comment in commentStore.getCommentsForCard(card.id)"
					:key="comment.id"
					:comment="comment"
					@doReload="loadComments" />
			</div>
			<!-- <InfiniteLoading :identifier="card.id" @infinite="infiniteHandler">
				<template #spinner>
					<div class="icon-loading" />
				</template>
				<template #no-more />
				<template #no-results />
			</InfiniteLoading> -->
		</ul>
		<div v-else-if="isLoading" class="icon icon-loading" />
		<div v-else class="emptycontent">
			<div :class="{ 'icon-comment': !error, 'icon-error': error }" />
			<p>{{ error || t('deck', 'No comments yet. Begin the discussion!') }}</p>
		</div>
	</div>
</template>

<script>
import { mapState } from 'pinia'
import { NcAvatar } from '@nextcloud/vue'
import CommentItem from './CommentItem.vue'
import CommentForm from './CommentForm.vue'
import { vInfiniteScroll } from '@vueuse/components'
import { getCurrentUser } from '@nextcloud/auth'
import { useCommentStore } from '../../stores/comment.js'
import { useBoardStore } from '../../stores/board.js'

export default {
	name: 'CardSidebarTabComments',
	components: {
		NcAvatar,
		CommentItem,
		CommentForm,
	},
	directives: {
		vInfiniteScroll,
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
	setup() {
		const commentStore = useCommentStore()
		return { commentStore }
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
		...mapState(useBoardStore, {
			currentBoard: 'currentBoard',
		}),
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
				if (this.commentStore.hasMoreComments(this.card.id)) {
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
			this.commentStore.setReplyTo(null)
			this.error = null
			this.isLoading = true
			try {
				await this.commentStore.fetchComments({ cardId: this.card.id })
				this.isLoading = false
				if (this.card.commentsUnread > 0) {
					await this.commentStore.markCommentsAsRead(this.card.id)
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
			await this.commentStore.createComment(commentObj)
			this.commentStore.setReplyTo(null)
			this.newComment = ''
			await this.loadComments()
		},
		async loadMore() {
			this.isLoading = true
			await this.commentStore.fetchMore({ cardId: this.card.id })
			this.isLoading = false
		},
		cancelReply() {
			this.commentStore.setReplyTo(null)
		},
	},
}
</script>

<style scoped lang="scss">
	@use '../../css/comments.scss';
</style>
