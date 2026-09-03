<!--
  - SPDX-FileCopyrightText: 2018 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<NcDialog :name="t('deck', 'Export {boardTitle}', {boardTitle: boardTitle})" @update:open="close">
		<div class="modal__content">
			<NcCheckboxRadioSwitch :checked.sync="exportFormat"
				value="json"
				type="radio"
				name="board_export_format">
				{{ t('deck', 'Export as JSON') }}
			</NcCheckboxRadioSwitch>
			<NcCheckboxRadioSwitch :checked.sync="exportFormat"
				value="csv"
				type="radio"
				name="board_export_format">
				{{ t('deck', 'Export as CSV') }}
			</NcCheckboxRadioSwitch>

			<p class="note">
				{{ t('deck', 'Note: Only the JSON format is supported for importing back into the Deck app.') }}
			</p>

			<fieldset class="options">
				<legend>{{ t('deck', 'Content to export') }}</legend>
				<NcCheckboxRadioSwitch :checked.sync="includeArchivedCards">
					{{ t('deck', 'Archived cards') }}
				</NcCheckboxRadioSwitch>
				<!-- Comments and attachments have no place in a flat list of cards -->
				<template v-if="exportFormat === 'json'">
					<NcCheckboxRadioSwitch :checked.sync="includeComments">
						{{ t('deck', 'Comments') }}
					</NcCheckboxRadioSwitch>
					<NcCheckboxRadioSwitch :checked.sync="includeAttachments">
						{{ t('deck', 'Attachments') }}
					</NcCheckboxRadioSwitch>
					<p class="note">
						{{ t('deck', 'Attachments are embedded in the export file. Leaving them out keeps the file small, but the export will no longer restore the board completely.') }}
					</p>
				</template>
			</fieldset>
		</div>

		<template #actions>
			<NcButton @click="close">
				{{ t('deck', 'Cancel') }}
			</NcButton>
			<NcButton type="primary" @click="exportBoard">
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
			includeArchivedCards: true,
			includeComments: true,
			includeAttachments: true,
		}
	},
	methods: {
		exportBoard() {
			this.$emit('export', this.exportFormat, {
				archivedCards: this.includeArchivedCards,
				comments: this.includeComments,
				attachments: this.includeAttachments,
			})
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
	color: var(--color-text-maxcontrast);
}

/* the element is only there to group the controls for assistive tech, the
   browser's default border would otherwise render as a stray rule */
fieldset.options {
	margin-top: 16px;
	border: none;
	padding: 0;
}

fieldset.options legend {
	font-weight: bold;
	margin-bottom: 4px;
	padding: 0;
	float: none;
	width: auto;
}
</style>
