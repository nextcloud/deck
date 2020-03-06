<template>
	<div v-if="reply" class="reply">
		<span class="reply--hint">{{ t('deck', 'In reply to') }} <UserBubble :user="comment.actorId" :display-name="comment.actorDisplayName" /></span>
		<RichText class="comment--content"
			:text="richText(comment)"
			:arguments="richArgs(comment)"
			:autolink="true" />
	</div>
	<li v-else class="comment">
		<template>
			<div class="comment--header">
				<Avatar :user="comment.actorId" />
				<span class="has-tooltip username">
					{{ comment.actorDisplayName }}
				</span>
				<Actions v-show="!edit" :force-menu="true">
					<ActionButton icon="icon-reply" @click="replyTo()">
						{{ t('deck', 'Reply') }}
					</ActionButton>
					<ActionButton v-if="canEdit" icon="icon-rename" @click="showUpdateForm()">
						{{ t('deck', 'Update') }}
					</ActionButton>
					<ActionButton v-if="canEdit" icon="icon-delete" @click="deleteComment()">
						{{ t('deck', 'Delete') }}
					</ActionButton>
				</Actions>
				<Actions v-if="edit">
					<ActionButton icon="icon-close" @click="hideUpdateForm" />
				</Actions>
				<div class="spacer" />
				<div class="timestamp">
					{{ relativeDate(comment.creationDateTime) }}
				</div>
			</div>
			<CommentItem v-if="comment.replyTo" :reply="true" :comment="comment.replyTo" />
			<div v-show="!edit" ref="richTextElement">
				<RichText
					class="comment--content"
					:text="richText(comment)"
					:arguments="richArgs(comment)"
					:autolink="true" />
			</div>
			<CommentForm v-if="edit" v-model="commentMsg" @submit="updateComment" />
		</template>
	</li>
</template>

<script>
import { Avatar, Actions, ActionButton, UserBubble } from '@nextcloud/vue'
import RichText from '@juliushaertl/vue-richtext'
import CommentForm from './CommentForm'
import { getCurrentUser } from '@nextcloud/auth'
import md5 from 'blueimp-md5'
import relativeDate from '../../mixins/relativeDate'

const AtMention = {
	name: 'AtMention',
	functional: true,
	render(createElement, context) {
		const { user, displayName } = context.props
		return createElement(
			'span',
			{ attrs: { 'data-at-embedded': true, 'contenteditable': false } },
			[createElement(UserBubble, { props: { user, displayName }, attrs: { 'data-mention-id': user } })]
		)
	},
}

export default {
	name: 'CommentItem',
	mixins: [ relativeDate ],
	components: {
		Avatar,
		UserBubble,
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
		reply: {
			type: Boolean,
			default: false,
		},
	},
	data() {
		return {
			edit: false,
			commentMsg: '',
		}
	},

	computed: {
		canEdit() {
			return this.comment.actorId === getCurrentUser().uid
		},
		richText() {
			return (comment) => {
				let message = this.parsedMessage(comment.message)
				comment.mentions.forEach((mention, index) => {
					// Currently only [a-z\-_0-9] are allowed inside of placeholders so we use a hash of the mention id as a unique identifier
					const hash = md5(mention.mentionId)
					message = message.split('@' + mention.mentionId + '').join(`{user-${hash}}`)
					message = message.split('@"' + mention.mentionId + '"').join(`{user-${hash}}`)

				})
				return message
			}
		},
		richArgs() {
			return (comment) => {
				const mentions = [...comment.mentions]
				const result = mentions.reduce((result, item, index) => {
					const itemKey = 'user-' + md5(item.mentionId)
					result[itemKey] = {
						component: AtMention,
						props: {
							user: item.mentionId,
							displayName: item.mentionDisplayName,
						},
					}
					return result
				}, {})
				return result
			}
		},
		parsedMessage() {
			return (message) => {
				const div = document.createElement('div')
				div.innerHTML = message
				return (div.textContent || div.innerText || '')
			}
		},
	},

	methods: {
		replyTo() {
			this.$store.dispatch('setReplyTo', this.comment)
		},
		showUpdateForm() {
			this.commentMsg = this.$refs.richTextElement.children[0].innerHTML
			this.edit = true
		},
		hideUpdateForm() {
			this.commentMsg = ''
			this.edit = false
		},
		async updateComment() {
			const data = {
				comment: this.commentMsg,
				cardId: this.comment.objectId,
				id: this.comment.id,
			}
			await this.$store.dispatch('updateComment', data)
			this.hideUpdateForm()
		},
		deleteComment() {
			const data = {
				id: this.comment.id,
				cardId: this.comment.objectId,
			}
			this.$store.dispatch('deleteComment', data)
		},
	},
}
</script>

<style scoped lang="scss">
	@import "../../css/comments";

	.reply {
		border-left: 3px solid var(--color-primary-element);
		padding-left: 5px;
		margin-left: 2px;

		.reply--hint {
			font-size: 0.9em;
			color: var(--color-text-lighter);
			vertical-align: top;
		}

		.comment--content {
			margin: 0;
		}
	}
	.comment--content::v-deep a {
		text-decoration: underline;
	}
</style>
