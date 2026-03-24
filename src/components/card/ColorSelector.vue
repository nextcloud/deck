<!--
  - SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<CardDetailEntry :label="t('deck', 'Assign a color to this card')" data-test="color-selector">
		<SelectColor slot="icon" :size="20" />
		<template>
			<NcColorPicker v-model="color" clearable>
				<button :style="{ backgroundColor: color }"
					class="color0 icon-colorpicker"
					:disabled="!canEdit"
					data-cy-color-actions />
			</NcColorPicker>
		</template>
	</CardDetailEntry>
</template>

<script>
import { defineComponent } from 'vue'
import { NcColorPicker } from '@nextcloud/vue'
import SelectColor from 'vue-material-design-icons/SelectColor.vue'
import CardDetailEntry from './CardDetailEntry.vue'

export default defineComponent({
	name: 'ColorSelector',
	components: {
		NcColorPicker,
		SelectColor,
		CardDetailEntry,
	},
	props: {
		card: {
			type: Object,
			default: null,
		},
		canEdit: {
			type: Boolean,
			default: false,
		},
	},
	computed: {
		color: {
			get() {
				return this.card.color ? '#' + this.card.color : ''
			},
			set(color) {
				this.$emit('change', color ? color.substring(1) : null)
			},
		},
	},
})
</script>
<style scoped lang="scss">
.color0 {
	width: 100px;
}
</style>
