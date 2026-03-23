<!--
  - SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<VueEasymde ref="editor"
		v-model="localValue"
		:configs="configs"
		@initialized="onInitialized"
		@input="onModelUpdate"
		@update:modelValue="onModelUpdate"
		@blur="$emit('blur', $event)" />
</template>

<script>
export default {
	name: 'DeckMarkdownEditor',
	model: {
		prop: 'value',
		event: 'input',
	},
	components: {
		VueEasymde: () => import('vue-easymde/dist/VueEasyMDE.common.js'),
	},
	props: {
		value: {
			type: String,
			default: undefined,
		},
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
			localValue: this.getExternalValue(),
		}
	},
	watch: {
		value(newValue) {
			this.syncLocalValue(newValue)
		},
		modelValue(newValue) {
			this.syncLocalValue(newValue)
		},
	},
	methods: {
		getExternalValue() {
			return this.modelValue !== undefined ? this.modelValue : (this.value ?? '')
		},
		syncLocalValue(newValue) {
			if (newValue === undefined || newValue === this.localValue) {
				return
			}

			this.localValue = newValue
		},
		onInitialized(...args) {
			this.$emit('initialized', ...args)
		},
		onModelUpdate(value) {
			this.localValue = value
			this.$emit('input', value)
			this.$emit('update:modelValue', value)
		},
		getEasyMde() {
			return this.$refs.editor?.easymde
		},
		getValue() {
			return this.getEasyMde()?.value() ?? this.localValue
		},
		setValue(value) {
			const easyMde = this.getEasyMde()
			if (easyMde) {
				easyMde.value(value)
			}

			this.localValue = value
			this.$emit('input', value)
			this.$emit('update:modelValue', value)
		},
	},
}
</script>