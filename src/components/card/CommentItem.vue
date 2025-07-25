<!--
  - SPDX-FileCopyrightText: 2019 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<div v-if="reply" class="reply" :class="{ 'reply--preview': preview }">
		<div class="reply--wrapper">
			<div class="reply--header">
				<div class="reply--hint">
					{{ t('deck', 'In reply to') }}
					<NcUserBubble :user="comment.actorId" :display-name="comment.actorDisplayName" />
				</div>
				<NcActions v-if="preview" class="reply--cancel">
					<NcActionButton icon="icon-close" @click="$emit('cancel')">
						{{ t('deck', 'Cancel reply') }}
					</NcActionButton>
				</NcActions>
			</div>
			<NcRichText class="comment--content"
				dir="auto"
				use-markdown
				:text="richText(comment)"
				:arguments="richArgs(comment)"
				:autolink="true" />
		</div>
	</div>
	<li v-else class="comment">
		<div class="comment--header">
			<NcAvatar :user="comment.actorId" />
			<span class="username">
				{{ comment.actorDisplayName }}
			</span>
			<NcActions v-show="!edit" :force-menu="true">
				<NcActionButton :close-after-click="true" @click="replyTo()">
					<template #icon>
						<ReplyIcon decorative />
					</template>
					{{ t('deck', 'Reply') }}
				</NcActionButton>
				<NcActionButton v-if="canEdit"
					icon="icon-rename"
					:close-after-click="true"
					@click="showUpdateForm()">
					{{ t('deck', 'Update') }}
				</NcActionButton>
				<NcActionButton v-if="canEdit"
					icon="icon-delete"
					:close-after-click="true"
					@click="deleteComment()">
					{{ t('deck', 'Delete') }}
				</NcActionButton>
			</NcActions>
			<NcActions v-if="edit">
				<NcActionButton icon="icon-close" @click="hideUpdateForm" />
			</NcActions>
			<div class="spacer" />
			<div class="timestamp"
				:aria-label="formattedTimestamp"
				:title="formattedTimestamp">
				{{ relativeDate(comment.creationDateTime) }}
			</div>
		</div>
		<CommentItem v-if="comment.replyTo" :reply="true" :comment="comment.replyTo" />
		<div v-show="!edit" ref="richTextElement">
			<NcRichText class="comment--content"
				dir="auto"
				use-markdown
				:text="richText(comment)"
				:arguments="richArgs(comment)"
				:autolink="true" />
		</div>
		<CommentForm v-if="edit"
			v-model="commentMsg"
			dir="auto"
			@submit="updateComment" />
	</li>
</template>

<script>
import { NcAvatar, NcActions, NcActionButton, NcRichText, NcUserBubble } from '@nextcloud/vue'
import CommentForm from './CommentForm.vue'
import { getCurrentUser } from '@nextcloud/auth'
import md5 from 'blueimp-md5'
import relativeDate from '../../mixins/relativeDate.js'
import ReplyIcon from 'vue-material-design-icons/ReplyOutline.vue'
import moment from 'moment'

const AtMention = {
	name: 'AtMention',
	functional: true,
	render(createElement, context) {
		const { user, displayName } = context.props
		return createElement(
			'span',
			{ attrs: { 'data-at-embedded': true, contenteditable: false } },
			[createElement(NcUserBubble, { props: { user, displayName }, attrs: { 'data-mention-id': user } })],
		)
	},
}

export default {
	name: 'CommentItem',
	components: {
		NcAvatar,
		NcUserBubble,
		NcActions,
		NcActionButton,
		CommentForm,
		NcRichText,
		ReplyIcon,
	},
	mixins: [relativeDate],
	props: {
		comment: {
			type: Object,
			default: undefined,
		},
		reply: {
			type: Boolean,
			default: false,
		},
		preview: {
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
		formattedTimestamp() {
			return t('deck', 'Created:') + ' ' + moment(this.comment.creationDateTime).format('LLLL')
		},
	},

	methods: {
		replyTo() {
			this.$store.dispatch('setReplyTo', this.comment)
		},
		showUpdateForm() {
			this.edit = true
			this.$nextTick(() => {
				this.commentMsg = this.$refs.richTextElement.children[0].innerHTML
			})
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
	@import '../../css/comments';

	.reply {
		margin: 0 0 0 var(--default-clickable-area);

		&.reply--preview {
			margin: 4px 0;
			padding: 8px;
			background-color: var(--color-background-hover);
			border-radius: var(--border-radius-large);

			.reply--wrapper {
				margin: 8px;
			}

			.reply--cancel {
				margin-right: -12px;
				margin-top: -12px;
			}
		}

		.reply--wrapper {
			border-left: 4px solid var(--color-border-dark);
			padding-left: 8px;
		}

		&:deep(.rich-text--wrapper) {
			margin-top: -3px;
			color: var(--color-text-lighter);
		}

		.reply--header {
			display: flex;
		}

		.reply--hint {
			color: var(--color-text-lighter);
			flex-grow: 1;
		}

		.comment--content {
			margin: 0;
		}
	}

	.comment--content:deep {
		a {
			text-decoration: underline;
		}

		p {
			margin-bottom: 1em;
		}
	}
</style>
