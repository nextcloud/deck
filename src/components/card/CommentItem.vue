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
			<!-- FIXME: Check if input is sanitized -->
			<p class="comment--content" v-html="parsedMessage" /><p />
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
import { Avatar, Actions, ActionButton } from '@nextcloud/vue'
import escapeHtml from 'escape-html'

export default {
	name: 'CommentItem',
	components: {
		Avatar,
		Actions,
		ActionButton,
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
		parsedMessage() {
			const div = document.createElement('div')
			div.innerHTML = this.comment.message
			let message = escapeHtml(div.textContent || div.innerText || '')
			// FIXME: We need a proper way to show user bubbles in the comment content
			// Either we split the text and render components for each part or we could try
			// to manually mount a component into the current components dom
			this.comment.mentions.forEach((mention) => {
				message = message.replace('@' + mention.mentionId + ' ', '<strong data-mention-id="' + mention.mentionId + '">@' + mention.mentionDisplayName + '</strong> ')
			})
			return message
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
