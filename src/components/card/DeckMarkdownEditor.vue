<!--
  - SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<VueEasymde ref="editor"
		v-model="localValue"
		:configs="configs"
		@initialized="onInitialized"
		@update:modelValue="onModelUpdate"
		@blur="$emit('blur', $event)" />
</template>

<script>
export default {
	name: 'DeckMarkdownEditor',
	components: {
		VueEasymde: () => import('vue-easymde/dist/VueEasyMDE.common.js'),
	},
	props: {
		modelValue: {
			type: String,
			default: '',
		},
		configs: {
			type: Object,
			required: true,
		},
	},
	data() {
		return {
			localValue: this.modelValue,
		}
	},
	watch: {
		modelValue(newValue) {
			if (newValue === this.localValue) {
				return
			}

			this.localValue = newValue
		},
	},
	methods: {
		onInitialized(...args) {
			this.$emit('initialized', ...args)
		},
		onModelUpdate(value) {
			this.localValue = value
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
			this.$emit('update:modelValue', value)
		},
	},
}
</script>