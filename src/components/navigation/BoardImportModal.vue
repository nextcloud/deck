<!--
  - SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<NcDialog :name="t('deck', 'Import board')" @update:open="close">
		<div class="modal__content">
			<p class="filename">
				{{ t('deck', 'Importing from {filename}', { filename: filename }) }}
			</p>

			<fieldset class="options">
				<legend>{{ t('deck', 'Content to import') }}</legend>
				<NcCheckboxRadioSwitch v-for="option in options"
					:key="option.key"
					:checked.sync="selection[option.key]"
					:disabled="option.requiresCards && !selection.importCards">
					{{ option.label }}
				</NcCheckboxRadioSwitch>
			</fieldset>

			<p class="note">
				{{ t('deck', 'Lists and labels are always imported. Deselect cards to create an empty board from this file, for example to use it as a template.') }}
			</p>
		</div>

		<template #actions>
			<NcButton @click="close">
				{{ t('deck', 'Cancel') }}
			</NcButton>
			<NcButton type="primary" @click="importBoard">
				{{ t('deck', 'Import') }}
			</NcButton>
		</template>
	</NcDialog>
</template>

<script>
import { NcButton, NcCheckboxRadioSwitch, NcDialog } from '@nextcloud/vue'

export default {
	name: 'BoardImportModal',
	components: {
		NcDialog,
		NcCheckboxRadioSwitch,
		NcButton,
	},
	props: {
		filename: {
			type: String,
			required: true,
		},
	},
	data() {
		return {
			selection: {
				importCards: true,
				importArchivedCards: true,
				importDoneState: true,
				importDueDates: true,
				importLabels: true,
				importAssignments: true,
				importComments: true,
				importAttachments: true,
				importSharing: true,
			},
		}
	},
	computed: {
		options() {
			return [
				{ key: 'importCards', label: t('deck', 'Cards') },
				{ key: 'importArchivedCards', label: t('deck', 'Archived cards'), requiresCards: true },
				{ key: 'importDoneState', label: t('deck', 'Completion state'), requiresCards: true },
				{ key: 'importDueDates', label: t('deck', 'Due and start dates'), requiresCards: true },
				{ key: 'importLabels', label: t('deck', 'Tags') },
				{ key: 'importAssignments', label: t('deck', 'Assigned users'), requiresCards: true },
				{ key: 'importComments', label: t('deck', 'Comments'), requiresCards: true },
				{ key: 'importAttachments', label: t('deck', 'Attachments'), requiresCards: true },
				{ key: 'importSharing', label: t('deck', 'Sharing') },
			]
		},
	},
	methods: {
		importBoard() {
			// Options that depend on cards are meaningless without them
			const selection = { ...this.selection }
			if (!selection.importCards) {
				for (const option of this.options) {
					if (option.requiresCards) {
						selection[option.key] = false
					}
				}
			}
			this.$emit('import', selection)
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

.filename {
	margin-bottom: 10px;
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
