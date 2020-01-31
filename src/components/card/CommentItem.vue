<template>
	<li class="comment">
		<template>
			<div class="comment--header">
				<Avatar :user="comment.actorId" />
				<span class="has-tooltip username">
					{{ comment.actorDisplayName }}
				</span>
				<Actions v-if="!edit" @click.stop.prevent>
					<ActionButton icon="icon-rename" @click="showUpdateForm()">
						{{ t('deck', 'Update') }}
					</ActionButton>
					<ActionButton icon="icon-delete" @click="deleteComment(comment.id)">
						{{ t('deck', 'Delete') }}
					</ActionButton>
				</Actions>
				<Actions v-else @click.stop.prevent>
					<ActionButton icon="icon-close" @click="hideUpdateForm" />
				</Actions>
			</div>
			<RichText v-if="!edit"
				class="comment--content"
				:text="richText"
				:arguments="richArgs"
				:autolink="true" />
			<CommentForm v-else v-model="commentMsg" @submit="updateComment" />
		</template>
	</li>
</template>

<script>
import { Avatar, Actions, ActionButton, UserBubble } from '@nextcloud/vue'
import RichText from '@juliushaertl/vue-richtext'
import CommentForm from './CommentForm'
export default {
	name: 'CommentItem',
	components: {
		Avatar,
		Actions,
		ActionButton,
		CommentForm,
		RichText,
	},
	props: {
		comment: {
			type: Object,
			default: undefined,
		},
	},
	data() {
		return {
			edit: false,
			commentMsg: '',
		}
	},

	computed: {
		richText() {
			let message = this.parsedMessage
			this.comment.mentions.forEach((mention, index) => {
				// FIXME: currently only [a-z\-_0-9] are allowed inside of placeholders
				message = message.split('@' + mention.mentionId + '').join(`{user-${mention.mentionId}}`)
			})
			return message
		},
		richArgs() {
			const mentions = [...this.comment.mentions]
			const result = mentions.reduce(function(result, item, index) {
				const itemKey = 'user-' + item.mentionId
				result[itemKey] = {
					component: UserBubble,
					props: {
						user: item.mentionId,
						displayName: item.mentionDisplayName,
					},
				}
				return result
			}, {})
			return result
		},
		parsedMessage() {
			const div = document.createElement('div')
			div.innerHTML = this.comment.message
			return (div.textContent || div.innerText || '')
		},
	},

	methods: {

		showUpdateForm() {
			this.commentMsg = this.comment.message
			this.edit = true
		},
		hideUpdateForm() {
			this.commentMsg = ''
			this.edit = false
		},
		async updateComment() {
			const data = {
				comment: this.commentMsg,
				cardId: this.comment.cardId,
				commentId: this.comment.id,
			}
			await this.$store.dispatch('updateComment', data)
			this.hideUpdateForm()
		},
		deleteComment(commentId) {
			const data = {
				commentId: commentId,
				cardId: this.comment.cardId,
			}
			this.$store.dispatch('deleteComment', data)
		},
	},
}
</script>

<style scoped lang="scss">
	@import "../../css/comments";

	.comment--content::v-deep a {
		text-decoration: underline;
	}
</style>
