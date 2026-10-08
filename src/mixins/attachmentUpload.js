import { showError } from '@nextcloud/dialogs'
import { formatFileSize } from '@nextcloud/files'
import PQueue from 'p-queue'
import { mapActions } from 'pinia'
/**
 * SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
import logger from '../logger.js'
import { useAttachmentStore } from '../stores/attachment.js'

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
				showError(t('deck', 'Failed to upload {name}', { name: file.name }) + ' - '
					+ t('deck', 'Maximum file size of {size} exceeded', { size: formatFileSize(this.maxUploadSize) }))
				event.target.value = ''
				return
			}

			this.uploadQueue[file.name] = {
				progress: 0,
				name: file.name,
			}
			const bodyFormData = new FormData()
			bodyFormData.append('cardId', this.cardId)
			bodyFormData.append('type', type)
			bodyFormData.append('file', file)
			await queue.add(async () => {
				try {
					await this.createAttachment({
						cardId: this.cardId,
						formData: bodyFormData,
						onUploadProgress: (e) => {
							const percentCompleted = Math.round((e.loaded * 100) / e.total)
							logger.debug('Upload progress', { file: file.name, percentCompleted })
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
		...mapActions(useAttachmentStore, [
			'createAttachment',
			'updateAttachment',
		]),

		overrideAttachment() {
			const bodyFormData = new FormData()
			bodyFormData.append('cardId', this.cardId)
			bodyFormData.append('type', 'deck_file')
			bodyFormData.append('file', this.file)
			this.updateAttachment({
				cardId: this.cardId,
				attachment: this.overwriteAttachment,
				formData: bodyFormData,
			})

			this.modalShow = false
		},

	},
}
