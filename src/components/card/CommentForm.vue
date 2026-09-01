<!--
  - SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<div class="comment-form">
		<NcRichContenteditable v-model="commentText"
			:auto-complete="autoComplete"
			:maxlength="1000"
			:user-data="members"
			@submit="submit" />
		<p v-if="error">
			{{ error }}
		</p>
	</div>
</template>

<script>
import { mapState } from 'vuex'
import { NcRichContenteditable } from '@nextcloud/vue'

export default {
	name: 'CommentForm',
	components: {
		NcRichContenteditable,
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
			const obj = {}
			this.currentBoard.users.forEach(user => {
				obj[user.uid] = {
					icon: 'icon-user',
					id: user.uid,
					label: user.displayname,
					source: 'users',
				}
			})
			return obj
		},
	},
	watch: {
		value(val) {
			this.commentText = val
		},
	},
	methods: {
		autoComplete(search, callback) {
			callback(Object.values(this.members))
		},
		validate(submit) {
			this.error = null
			const content = this.commentText
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
				let mentionValue
				if (mention.attributes['data-at-embedded'].value === 'true') {
					mentionValue = mention.parentNode.parentNode.querySelector('.user-bubble__wrapper').attributes['data-mention-id'].value
				} else {
					mentionValue = mention.firstElementChild.attributes['data-mention-id'].value
				}
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

<style lang="scss">
	@import '../../css/comments';

	[class^="_tribute-container-autocomplete_"],
	[class*=" _tribute-container-autocomplete_"],
	[class*="_tribute-container-autocomplete_"] {
		z-index: 9999 !important;
	}
</style>
