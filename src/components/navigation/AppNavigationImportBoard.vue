<!--
  - SPDX-FileCopyrightText: 2018 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<div>
		<NcAppNavigationItem :name="t('deck', 'Import board')"
			icon="icon-upload"
			:allow-collapse="true"
			:open="menuOpen"
			@update:open="menuOpen = $event">
			<template #default>
				<NcAppNavigationItem :name="t('deck', 'From Nextcloud')"
					@click.prevent.stop="importFromNextcloud">
					<template #icon>
						<CloudUploadIcon :size="20" />
					</template>
				</NcAppNavigationItem>
				<NcAppNavigationItem :name="t('deck', 'From device')"
					@click.prevent.stop="importFromDevice">
					<template #icon>
						<UploadIcon :size="20" />
					</template>
				</NcAppNavigationItem>
			</template>
		</NcAppNavigationItem>
		<input ref="fileInput"
			type="file"
			accept="application/json,.csv,text/csv"
			style="display: none;"
			@change="onLocalFileSelected">
		<CsvImportModal v-if="importModalOpen"
			:importing="importing"
			:messages="importMessages"
			:errors="importErrors"
			:title="t('deck', 'Import board')"
			@close="onCloseImportModal" />
	</div>
</template>

<script>
import { NcAppNavigationItem } from '@nextcloud/vue'
import { getFilePickerBuilder, FilePickerType } from '@nextcloud/dialogs'
import { getClient, getRootPath } from '@nextcloud/files/dav'
import CloudUploadIcon from 'vue-material-design-icons/CloudUpload.vue'
import UploadIcon from 'vue-material-design-icons/Upload.vue'
import CsvImportModal from './CsvImportModal.vue'

const davClient = getClient()

export default {
	name: 'AppNavigationImportBoard',
	components: {
		NcAppNavigationItem,
		CsvImportModal,
		CloudUploadIcon,
		UploadIcon,
	},
	props: {
		loading: {
			type: Boolean,
			default: false,
		},
	},
	data() {
		return {
			menuOpen: false,
			importModalOpen: false,
			importing: false,
			importMessages: [],
			importErrors: [],
		}
	},
	methods: {
		async importFromNextcloud() {
			this.menuOpen = false
			try {
				const picker = getFilePickerBuilder(t('deck', 'Select file to import'))
					.setMultiSelect(false)
					.setMimeTypeFilter(['application/json', 'text/csv', 'text/plain'])
					.setType(FilePickerType.Choose)
					.build()
				const path = await picker.pick()
				const contents = await davClient.getFileContents(getRootPath() + path)
				const filename = path.split('/').pop()
				const mime = filename.endsWith('.csv') ? 'text/csv' : 'application/json'
				const file = new File([contents], filename, { type: mime })
				await this.doImport(file)
			} catch (e) {
				// FilePicker closed without selection
			}
		},
		importFromDevice() {
			this.menuOpen = false
			this.$refs.fileInput.value = ''
			this.$refs.fileInput.click()
		},
		async onLocalFileSelected(event) {
			const file = event.target.files[0]
			if (file) {
				await this.doImport(file)
			}
		},
		async doImport(file) {
			this.importModalOpen = true
			this.importing = true
			this.importMessages = []
			this.importErrors = []

			try {
				const result = await this.$store.dispatch('importBoard', file)
				if (result?.message) {
					this.importErrors = [result.message]
				} else if (result instanceof Error) {
					this.importErrors = [t('deck', 'Failed to import board')]
				} else {
					this.importErrors = result?.import?.errors ?? []
					const board = result?.board
					if (board) {
						this.importMessages.push(t('deck', 'Board "{title}" created.', { title: board.title }))
						const stackCount = board.stacks?.length ?? 0
						if (stackCount > 0) {
							this.importMessages.push(n('deck',
								'%n stack created.',
								'%n stacks created.',
								stackCount))
						}
					}
				}
			} catch (e) {
				this.importErrors = [t('deck', 'Failed to import board')]
				console.error(e)
			}

			this.importing = false
		},
		onCloseImportModal() {
			this.importModalOpen = false
		},
	},
}
</script>
