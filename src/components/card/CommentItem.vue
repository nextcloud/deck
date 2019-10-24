<template>
	<li class="comment">
		<CommentItemEdit
			v-if="edit"
			:comment="comment"
			@update="updateComment"
			@close="edit = false"
		/>

		<template v-else>

			<div id="userDiv">
				<avatar :user="comment.uId" />
				<span class="username">{{ comment.uId }}</span>
				<Actions class="action" @click.stop.prevent>
					<ActionButton icon="icon-rename" @click="edit = true">{{ t('deck', 'Update') }}</ActionButton>
					<ActionButton icon="icon-delete" @click="deleteComment(comment.id)">{{ t('deck', 'Delete') }}</ActionButton>
				</Actions>
				<span class="creationDateTime">{{ getTime(comment.creationDateTime) }}</span>
			</div>
			<div class="message">{{ comment.message }}</div>
		</template>
	</li>
</template>

<script>
import { Avatar } from 'nextcloud-vue'
import { Actions } from 'nextcloud-vue/dist/Components/Actions'
import { ActionButton } from 'nextcloud-vue/dist/Components/ActionButton'
import CommentItemEdit from './CommentItemEdit'

export default {
	name: 'CommentItem',
	components: {
		Avatar,
		Actions,
		ActionButton,
		CommentItemEdit
	},
	props: {
		comment: {
			type: Object,
			default: undefined
		}
	},
	data() {
		return {
			edit: false
		}
	},

	methods: {
		getTime(timestamp) {
			return OC.Util.relativeModifiedDate(timestamp)
		},
		updateComment(newMsg) {
			let data = {
				comment: newMsg,
				cardId: this.comment.cardId,
				commentId: this.comment.id
			}
			this.$store.dispatch('updateComment', data)
			this.edit = false
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

<style scoped lang="scss">
	#userDiv {
		display: flex;
		align-items: center;
		.username {
			padding: 12px 9px;
			opacity: .5;
		}
		.creationDateTime {
			flex-grow: 1;
			text-align: right;
			opacity: .5;
		}
		.action {
			opacity: .3;
		}

	}

	form {
		display: flex
	}

	.comment {
		border-top: 1px solid var(--color-border);
		padding: 10px 0 15px;
	}

	.message {
		padding-left: 40px;
		word-wrap: break-word;
		overflow-wrap: break-word;
	}

</style>
