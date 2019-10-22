<template>
	<li>
		<hr>
		<form v-if="edit" @submit.prevent="updateComment">
			<input v-model="commentMsg" type="text" autofocus
				required>
			<input v-tooltip="t('deck', 'Save')" class="icon-confirm" type="submit"
				value="">
			<input type="submit" value="" class="icon-close"
				@click.stop.prevent="hideUpdateForm">
		</form>

		<template v-else>
			<avatar :user="comment.displayname" />
			<span>{{ comment.uId }}</span>
			<Actions @click.stop.prevent>
				<ActionButton icon="icon-rename" @click="showUpdateForm()">{{ t('deck', 'Update') }}</ActionButton>
				<ActionButton icon="icon-delete" @click="deleteComment(comment.id)">{{ t('deck', 'Delete') }}</ActionButton>
			</Actions>
			{{ comment.message }}
		</template>
	</li>
</template>

<script>
import { Avatar } from 'nextcloud-vue'
import { Actions } from 'nextcloud-vue/dist/Components/Actions'
import { ActionButton } from 'nextcloud-vue/dist/Components/ActionButton'
export default {
	name: 'CommentItem',
	components: {
		Avatar,
		Actions,
		ActionButton
	},
	props: {
		comment: {
			type: Object,
			default: undefined
		}
	},
	data() {
		return {
			edit: false,
			commentMsg: ''
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
			let data = {
				comment: this.commentMsg,
				cardId: this.comment.cardId,
				commentId: this.comment.id
			}
			this.$store.dispatch('updateComment', data)
			this.hideUpdateForm()
		},
		deleteComment(commentId) {
			let data = {
				commentId: commentId,
				cardId: this.comment.cardId
			}
			this.$store.dispatch('deleteComment', data)
		}
	}
}
</script>

<style lang="scss">
	form {
		display: flex
	}
	.mention {
		font-weight: bold;
	}

</style>
