<!--
  - SPDX-FileCopyrightText: 2018 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<div>
		<NcAppNavigationItem :name="t('deck', 'Import board')" icon="icon-upload" @click.prevent.stop="startImportBoard" />
		<input ref="fileInput"
			type="file"
			accept="application/json"
			style="display: none;"
			@change="onFileSelected">
		<BoardImportModal v-if="selectedFile"
			:filename="selectedFile.name"
			@import="doImportBoard"
			@close="cancelImport" />
	</div>
</template>

<script>
import { NcAppNavigationItem } from '@nextcloud/vue'
import { showError } from '../../helpers/errors.js'
import { showSuccess, showLoading } from '@nextcloud/dialogs'
import { useBoardStore } from '../../stores/board.js'
import BoardImportModal from './BoardImportModal.vue'

export default {
	name: 'AppNavigationImportBoard',
	components: { NcAppNavigationItem, BoardImportModal },
	props: {
		loading: {
			type: Boolean,
			default: false,
		},
	},
	data() {
		return {
			value: '',
			selectedFile: null,
		}
	},
	methods: {
		startImportBoard() {
			this.$refs.fileInput.value = ''
			this.$refs.fileInput.click()
		},
		onFileSelected(event) {
			const file = event.target.files[0]
			if (file) {
				this.selectedFile = file
			}
		},
		cancelImport() {
			this.selectedFile = null
		},
		async doImportBoard(options) {
			const file = this.selectedFile
			this.selectedFile = null
			if (!file) {
				return
			}

			const loadingToast = showLoading(t('deck', 'Importing board...'))
			const result = await useBoardStore().importBoard(file, options)
			loadingToast.hideToast()
			if (result?.message) {
				showError(result)
			} else {
				showSuccess(t('deck', 'Board imported successfully'))
			}
		},
	},
}
</script>
