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
