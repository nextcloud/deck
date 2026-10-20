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
			@change="doImportBoard">
	</div>
</template>

<script>
import { NcAppNavigationItem } from '@nextcloud/vue'
import { showError } from '../../helpers/errors.js'
import { showSuccess, showLoading } from '@nextcloud/dialogs'

export default {
	name: 'AppNavigationImportBoard',
	components: { NcAppNavigationItem },
	props: {
		loading: {
			type: Boolean,
			default: false,
		},
	},
	data() {
		return {
			value: '',
		}
	},
	methods: {
		startImportBoard() {
			this.$refs.fileInput.value = ''
			this.$refs.fileInput.click()
		},
		async doImportBoard(event) {
			const file = event.target.files[0]
			if (file) {
				const loadingToast = showLoading(t('deck', 'Importing board...'))
				const result = await this.$store.dispatch('importBoard', file)
				loadingToast.hideToast()
				if (result?.message) {
					showError(result)
				} else {
					showSuccess(t('deck', 'Board imported successfully'))
				}
			}
		},
	},
}
</script>
