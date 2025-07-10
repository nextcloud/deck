/**
 * SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
import { showError } from '@nextcloud/dialogs'
import { formatFileSize } from '@nextcloud/files'
// eslint-disable-next-line import/no-unresolved
import PQueue from 'p-queue'

const queue = new PQueue({ concurrency: 2 })

export default {
	data() {
		return {
			uploadQueue: {},
		}
	},
	methods: {
		async onLocalAttachmentSelected(file, type) {
			if (this.maxUploadSize > 0 && file.size > this.maxUploadSize) {
				showError(
					t('deck', 'Failed to upload {name}', { name: file.name }) + ' - '
						+ t('deck', 'Maximum file size of {size} exceeded', { size: formatFileSize(this.maxUploadSize) }),
				)
				event.target.value = ''
				return
			}

			this.uploadQueue[file.name] = { name: file.name, progress: 0 }

			const bodyFormData = new FormData()
			bodyFormData.append('cardId', this.cardId)
			bodyFormData.append('type', type)
			bodyFormData.append('file', file)
			await queue.add(async () => {
				try {
					await this.$store.dispatch('createAttachment', {
						cardId: this.cardId,
						formData: bodyFormData,
						onUploadProgress: (e) => {
							const percentCompleted = Math.round((e.loaded * 100) / e.total)
							console.debug(percentCompleted)
							this.uploadQueue[file.name].progress = percentCompleted
						},
					})
				} catch (err) {
					if (err.response.data.status === 409) {
						this.overwriteAttachment = err.response.data.data
						this.modalShow = true
					} else {
						showError(err.response.data ? err.response.data.message : 'Failed to upload file')
					}
				}
				delete this.uploadQueue[file.name]
			})

		},

		overrideAttachment() {
			const bodyFormData = new FormData()
			bodyFormData.append('cardId', this.cardId)
			bodyFormData.append('type', 'deck_file')
			bodyFormData.append('file', this.file)
			this.$store.dispatch('updateAttachment', {
				cardId: this.cardId,
				attachment: this.overwriteAttachment,
				formData: bodyFormData,
			})

			this.modalShow = false
		},

	},
}
