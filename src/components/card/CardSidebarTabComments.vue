<template>
	<div>
		<div class="comment--header">
			<Avatar :user="card.owner.uid" />
			<span class="has-tooltip username">
				{{ card.owner.displayname }}
			</span>
		</div>

		<div class="comment-form">
			<form @submit.prevent="createComment">
				<At ref="at"
					v-model="newComment"
					:members="members"
					name-key="primaryKey"
					:tab-select="true">
					<template v-slot:item="s">
						<Avatar :user="s.item.uid" />
						<span v-text="s.item.displayname" />
					</template>
					<template v-slot:embeddedItem="scope">
						<span>
							<UserBubble v-if="scope.current.primaryKey"
								:data-mention-id="scope.current.primaryKey"
								:user="scope.current.primaryKey"
								:display-name="scope.current.displayname" />
						</span>
					</template>
					<div ref="contentEditable" contenteditable />
				</At>
				<input v-tooltip="t('deck', 'Save')"
					class="icon-confirm"
					type="submit"
					value="">
			</form>
		</div>

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
import { Avatar, UserBubble } from '@nextcloud/vue'
import CommentItem from './CommentItem'
import InfiniteLoading from 'vue-infinite-loading'
import At from 'vue-at'
import { rawToParsed } from '../../helpers/mentions'

export default {
	name: 'CardSidebarTabComments',
	components: {
		Avatar,
		CommentItem,
		InfiniteLoading,
		At,
		UserBubble,
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
		async createComment() {
			const content = this.contentEditableToParsed()
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

		/**
		 * All credits for this go to the talk app
		 * https://github.com/nextcloud/spreed/blob/e69740b372e17eec4541337b47baa262a5766510/src/components/NewMessageForm/NewMessageForm.vue#L100-L143
		 */
		contentEditableToParsed() {
			const mentions = this.$refs.contentEditable.querySelectorAll('span[data-at-embedded]')
			mentions.forEach(mention => {
				// FIXME Adding a space after the mention should be improved to
				// do it or not based on the next element instead of always
				// adding it.
				mention.replaceWith('@' + mention.firstElementChild.attributes['data-mention-id'].value + ' ')
			})

			return rawToParsed(this.$refs.contentEditable.innerHTML)
		},
	},
}
</script>

<style scoped lang="scss">
	@import "../../css/comments";

	.atwho-wrap {
		width: 100%;
		& > div[contenteditable] {
			width: 100%;

			&::v-deep > span > div {
				vertical-align: middle;
			}
		}
	}
</style>
