<template>
	<li class="comment">
		<template v-if="!edit">
			<div class="comment--header">
				<Avatar :user="comment.actorId" />
				<span class="has-tooltip username">
					{{ comment.actorDisplayName }}
				</span>
				<Actions @click.stop.prevent>
					<ActionButton icon="icon-rename" @click="showUpdateForm()">
						{{ t('deck', 'Update') }}
					</ActionButton>
					<ActionButton icon="icon-delete" @click="deleteComment(comment.id)">
						{{ t('deck', 'Delete') }}
					</ActionButton>
				</Actions>
			</div>
			<RichText :text="richText" :arguments="richArgs" />
		</template>
		<form v-else @submit.prevent="updateComment">
			<input v-model="commentMsg"
				type="text"
				autofocus
				required>
			<input v-tooltip="t('deck', 'Save')"
				class="icon-confirm"
				type="submit"
				value="">
			<input type="submit"
				value=""
				class="icon-close"
				@click.stop.prevent="hideUpdateForm">
		</form>
	</li>
</template>

<script>
import { Avatar, Actions, ActionButton, UserBubble } from '@nextcloud/vue'
import escapeHtml from 'escape-html'
import RichText from '@juliushaertl/vue-richtext'

export default {
	name: 'CommentItem',
	components: {
		Avatar,
		Actions,
		ActionButton,
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
				message = message.replace('@' + mention.mentionId + '', `{user${index}}`)
			})
			return message
		},
		richArgs() {
			const mentions = [...this.comment.mentions]
			// TODO mentions are set once per user, so when mentioning the same user multiple times this doesn't work
			const result = mentions.reduce(function(result, item, index) {
				const itemKey = 'user' + index
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
			return escapeHtml(div.textContent || div.innerText || '')
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
		updateComment() {
			const data = {
				comment: this.commentMsg,
				cardId: this.comment.cardId,
				commentId: this.comment.id,
			}
			this.$store.dispatch('updateComment', data)
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
</style>
