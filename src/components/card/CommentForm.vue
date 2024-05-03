<!--
  - SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<div class="comment-form">
		<form @submit.prevent="submit">
			<At ref="at"
				v-model="commentText"
				:members="members"
				name-key="displayname"
				:tab-select="true">
				<template #item="s">
					<NcAvatar class="atwho-li--avatar" :user="s.item.uid" :size="24" />
					<span class="atwho-li--name" v-text="s.item.displayname" />
				</template>
				<template #embeddedItem="scope">
					<span>
						<NcUserBubble v-if="scope.current.uid"
							:data-mention-id="scope.current.uid"
							:user="scope.current.uid"
							:display-name="scope.current.displayname" />
					</span>
				</template>
				<div ref="contentEditable"
					dir="auto"
					class="comment-form__contenteditable"
					contenteditable
					@keydown.enter="handleKeydown"
					@paste="onPaste"
					@blur="error = null"
					@input="validate()" />
			</At>
			<input :title="t('deck', 'Save')"
				class="icon-confirm"
				type="submit"
				value=""
				:disabled="commentText.length === null || error">
			<slot />
		</form>
		<p v-if="error">
			{{ error }}
		</p>
	</div>
</template>

<script>
import { mapState } from 'vuex'
import { NcUserBubble, NcAvatar } from '@nextcloud/vue'
import At from 'vue-at'
import { rawToParsed } from '../../helpers/mentions.js'

export default {
	name: 'CommentForm',
	components: {
		At,
		NcAvatar,
		NcUserBubble,
	},
	props: {
		value: {
			type: String,
			default: '',
		},
	},
	data() {
		return {
			commentText: this.value,
			error: null,
		}
	},
	computed: {
		...mapState({
			currentBoard: state => state.currentBoard,
		}),
		members() {
			return this.currentBoard.users
		},
	},
	watch: {
		value(val) {
			this.commentText = val
		},
	},
	methods: {
		validate(submit) {
			this.error = null
			const content = this.contentEditableToParsed()
			if (submit && content.length === 0) {
				this.error = t('deck', 'The comment cannot be empty.')
			}
			if (content.length > 1000) {
				this.error = t('deck', 'The comment cannot be longer than 1000 characters.')
			}
			return this.error === null ? content : null
		},
		submit() {
			const content = this.validate(true)
			if (content) {
				// We need the plain text representation for the input event as otherwise it will propagate back to the contenteditable
				// The input event is only used for change detection to make sure that the input is reset after posting the comment
				const temp = document.createElement('div')
				temp.innerHTML = content
				const text = temp.textContent || temp.innerText || ''
				this.$emit('input', text)
				this.$emit('submit', text)
			}
		},
		/* All credits for this go to the talk app
		 * https://github.com/nextcloud/spreed/blob/e69740b372e17eec4541337b47baa262a5766510/src/components/NewMessageForm/NewMessageForm.vue#L100-L143
		 */
		contentEditableToParsed() {
			if (!this.$refs.contentEditable) {
				return
			}
			const node = this.$refs.contentEditable.cloneNode(true)
			const mentions = node.querySelectorAll('span[data-at-embedded]')
			mentions.forEach(mention => {
				// FIXME Adding a space after the mention should be improved to
				// do it or not based on the next element instead of always
				// adding it.
				// FIXME user names can contain spaces, in that case they need to be wrapped @"user name" [a-zA-Z0-9\ _\.@\-']+
				const mentionValue = mention.firstElementChild.attributes['data-mention-id'].value
				if (mentionValue.indexOf(' ') !== -1) {
					mention.replaceWith(' @"' + mentionValue + '" ')
				} else {
					mention.replaceWith(' @' + mentionValue + ' ')
				}
			})

			return rawToParsed(node.innerHTML)
		},

		/**
		 * Emits the submit event when enter is pressed (look
		 * at the v-on in the template) unless shift is pressed:
		 * in this case a new line will be created.
		 *
		 * @param {object} event the event object;
		 */
		handleKeydown(event) {
			// Prevent submit event when vue-at panel is open, as that should
			// just select the mention from the panel.
			if (this.atwho) {
				return
			}

			// TODO: add support for CTRL+ENTER new line
			if (!(event.shiftKey)) {
				event.preventDefault()
				this.submit()
			}
		},

		onPaste(e) {
			e.preventDefault()
			const text = e.clipboardData.getData('text/plain')
			document.execCommand('insertText', false, text)
		},
	},
}
</script>

<style scoped lang="scss">
	@import '../../css/comments';

	.comment-form__contenteditable {
		word-break: break-word;
		border-radius: var(--border-radius-large)
	}

	.atwho-wrap {
		width: 100%;
		& > div[contenteditable] {
			width: 100%;

			&:deep > span > div {
				vertical-align: middle;
			}
		}
	}

	.comment-form:deep(.atwho-li) {
		height: 32px;
	}

	.atwho-li--avatar {
		margin-right: 10px;
	}
</style>
