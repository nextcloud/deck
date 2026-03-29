<!--
  - SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<div class="vue-easymde">
		<textarea ref="textarea"
			class="vue-easymde-textarea"
			:value="localValue"
			@input="onTextareaInput($event.target.value)" />
	</div>
</template>

<script>
import EasyMDE from 'easymde'
import 'easymde/dist/easymde.min.css'

export default {
	name: 'DeckMarkdownEditor',
	emits: ['initialized', 'update:modelValue', 'blur'],
	props: {
		modelValue: {
			type: String,
			default: undefined,
		},
		configs: {
			type: Object,
			required: true,
		},
	},
	data() {
		return {
			localValue: this.normalizeValue(this.modelValue),
			easyMde: null,
			isInnerUpdate: false,
		}
	},
	watch: {
		modelValue(newValue) {
			this.syncLocalValue(newValue)
		},
	},
	mounted() {
		this.initializeEditor()
	},
	beforeUnmount() {
		this.easyMde = null
	},
	methods: {
		normalizeValue(value) {
			if (typeof value === 'string') {
				return value
			}

			if (value instanceof String) {
				return value.valueOf()
			}

			if (value?.target?.value !== undefined) {
				return value.target.value
			}

			if (typeof value?.then === 'function') {
				return this.localValue ?? ''
			}

			if (value === undefined || value === null) {
				return ''
			}

			return this.localValue ?? ''
		},
		initializeEditor() {
			const textarea = this.$refs.textarea
			if (!textarea) {
				return
			}

			const editorConfig = {
				element: textarea,
				initialValue: this.localValue,
				...(this.configs || {}),
			}

			this.easyMde = new EasyMDE(editorConfig)
			this.easyMde.codemirror.on('change', (_instance, changeObject) => {
				if (changeObject.origin === 'setValue') {
					return
				}

				const nextValue = this.easyMde.value()
				this.isInnerUpdate = true
				this.localValue = nextValue
				this.$emit('update:modelValue', nextValue)
			})
			this.easyMde.codemirror.on('blur', () => {
				this.$emit('blur', this.easyMde.value())
			})

			this.$nextTick(() => {
				this.$emit('initialized', this.easyMde)
			})
		},
		syncLocalValue(newValue) {
			if (newValue === undefined) {
				return
			}

			const normalizedValue = this.normalizeValue(newValue)
			if (normalizedValue === this.localValue) {
				this.isInnerUpdate = false
				return
			}

			this.localValue = normalizedValue
			if (this.easyMde && !this.isInnerUpdate) {
				this.easyMde.value(normalizedValue)
			}

			this.isInnerUpdate = false
		},
		onTextareaInput(value) {
			const normalizedValue = this.normalizeValue(value)
			this.localValue = normalizedValue
			this.$emit('update:modelValue', normalizedValue)
		},
		getEasyMde() {
			return this.easyMde
		},
		getValue() {
			return this.easyMde?.value() ?? this.localValue
		},
		setValue(value) {
			const normalizedValue = this.normalizeValue(value)
			if (this.easyMde) {
				this.isInnerUpdate = true
				this.easyMde.value(normalizedValue)
			}

			this.localValue = normalizedValue
			this.$emit('update:modelValue', normalizedValue)
		},
	},
}
</script>

<style scoped>
.vue-easymde-textarea {
	display: none;
}
</style>

<style>
.vue-easymde, .CodeMirror {
	border: none;
	margin: 0;
	padding: 0;
	background-color: var(--color-main-background);
	color: var(--color-main-text);
	width: 100%;
}

.CodeMirror-placeholder {
	color: var(--color-text-maxcontrast);
}

.CodeMirror-cursor {
	border-left: 1px solid var(--color-main-text);
}

.CodeMirror-selected,
.CodeMirror-line::selection, .CodeMirror-line>span::selection, .CodeMirror-line>span>span::selection {
	background: var(--color-primary-element) !important;
	color: var(--color-primary-element-text) !important;
}

.editor-preview,
.editor-statusbar {
	display: none;
}

.vue-easymde .cm-s-easymde .cm-link {
	color: var(--color-main-text);
}

.vue-easymde .cm-s-easymde .cm-string.cm-url,
.vue-easymde .cm-s-easymde .cm-formatting.cm-link,
.vue-easymde .cm-s-easymde .cm-formatting.cm-url,
.vue-easymde .cm-s-easymde .cm-formatting.cm-image {
	color: var(--color-text-maxcontrast);
}
</style>
