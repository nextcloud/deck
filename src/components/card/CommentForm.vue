<!--
  - SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<div class="comment-form" :dir="contentDir">
		<NcRichContenteditable v-model="commentText"
			:auto-complete="autoComplete"
			:maxlength="1000"
			:user-data="members"
			@submit="submit" />
		<NcButton v-show="hasContent"
			type="tertiary"
			:aria-label="t('deck', 'Submit')"
			:title="t('deck', 'Submit')"
			class="comment-form__submit"
			@click="submit">
			<template #icon>
				<ArrowRightIcon :size="20" />
			</template>
		</NcButton>
		<p v-if="error">
			{{ error }}
		</p>
	</div>
</template>

<script>
import { mapState } from 'vuex'
import { NcButton, NcRichContenteditable } from '@nextcloud/vue'
import ArrowRightIcon from 'vue-material-design-icons/ArrowRight.vue'

export default {
	name: 'CommentForm',
	components: {
		ArrowRightIcon,
		NcButton,
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
		hasContent() {
			return this.commentText.trim().length > 0
		},
		// Derive the form direction from the first strong character the user
		// typed so the submit button (positioned with inset-inline-end) sits on
		// the correct side: right for LTR text, left for Persian/Arabic. The
		// editor input declares its own dir="auto", which a dir="auto" ancestor
		// would skip, so we compute it here instead. Returns null while empty so
		// the form simply inherits the surrounding UI direction.
		contentDir() {
			const text = this.commentText.replace(/<[^>]*>/g, '')
			for (const ch of text) {
				const code = ch.codePointAt(0)
				// Latin letters (Basic Latin, Latin-1 Supplement, Latin
				// Extended-A/Additional) → left-to-right.
				if ((code >= 0x41 && code <= 0x5A) || (code >= 0x61 && code <= 0x7A)
					|| (code >= 0xC0 && code <= 0x24F) || (code >= 0x1E00 && code <= 0x1EFF)) {
					return 'ltr'
				}
				// Hebrew, Arabic, Arabic Supplement/Extended-A and the
				// Hebrew/Arabic presentation forms → right-to-left.
				if ((code >= 0x0591 && code <= 0x05F4) || (code >= 0x0600 && code <= 0x06FF)
					|| (code >= 0x0750 && code <= 0x077F) || (code >= 0x08A0 && code <= 0x08FF)
					|| (code >= 0xFB1D && code <= 0xFDFD) || (code >= 0xFE70 && code <= 0xFEFC)) {
					return 'rtl'
				}
			}
			return null
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
	},
}
</script>

<style lang="scss">
	@import '../../css/comments.scss';

	[class^="_tribute-container-autocomplete_"],
	[class*=" _tribute-container-autocomplete_"],
	[class*="_tribute-container-autocomplete_"] {
		z-index: 9999 !important;
	}

	.comment-form {
		position: relative;

		.comment-form__submit {
			position: absolute;
			bottom: var(--default-grid-baseline);
			inset-inline-end: var(--default-grid-baseline);
			z-index: 1;
		}

		// Point the send arrow toward the writing direction in RTL.
		&[dir="rtl"] .comment-form__submit .arrow-right-icon {
			transform: scaleX(-1);
		}

		// Add padding to prevent text from going under the button
		:deep(.rich-content-editor__input) {
			padding-inline-end: calc(var(--default-clickable-area) + var(--default-grid-baseline));
		}
	}
</style>
