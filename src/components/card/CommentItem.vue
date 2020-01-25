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
			<p class="comment--content" v-html="comment.message" /><p />
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
