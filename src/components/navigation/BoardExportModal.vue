<!--
  - SPDX-FileCopyrightText: 2018 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<NcDialog :name="t('deck', 'Export {boardTitle}', {boardTitle: boardTitle})" @update:open="close">
		<div class="modal__content">
			<NcCheckboxRadioSwitch v-model="exportFormat"
				value="json"
				type="radio"
				name="board_export_format">
				{{ t('deck', 'Export as JSON') }}
			</NcCheckboxRadioSwitch>
			<NcCheckboxRadioSwitch v-model="exportFormat"
				value="csv"
				type="radio"
				name="board_export_format">
				{{ t('deck', 'Export as CSV') }}
			</NcCheckboxRadioSwitch>

			<p class="note">
				{{ t('deck', 'Note: Only the JSON format is supported for importing back into the Deck app.') }}
			</p>
		</div>

		<template #actions>
			<NcButton @click="close">
				{{ t('deck', 'Cancel') }}
			</NcButton>
			<NcButton variant="primary" @click="exportBoard">
				{{ t('deck', 'Export') }}
			</NcButton>
		</template>
	</NcDialog>
</template>

<script>
import { NcButton, NcCheckboxRadioSwitch, NcDialog } from '@nextcloud/vue'

export default {
	name: 'BoardExportModal',
	components: {
		NcDialog,
		NcCheckboxRadioSwitch,
		NcButton,
	},
	props: {
		boardTitle: {
			type: String,
			default: 'Board',
		},
	},
	data() {
		return {
			exportFormat: 'json',
		}
	},
	methods: {
		exportBoard() {
			this.$emit('export', this.exportFormat)
			this.close()
		},
		close() {
			this.$emit('close')
		},
	},
}
</script>

<style scoped>
.modal__content {
	margin: 20px;
}

p.note {
	margin-top: 10px;
}
</style>
