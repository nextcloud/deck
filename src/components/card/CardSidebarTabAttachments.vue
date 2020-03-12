<!--
  - @copyright Copyright (c) 2020 Julius Härtl <jus@bitgrid.net>
  -
  - @author Julius Härtl <jus@bitgrid.net>
  -
  - @license GNU AGPL version 3 or any later version
  -
  - This program is free software: you can redistribute it and/or modify
  - it under the terms of the GNU Affero General Public License as
  - published by the Free Software Foundation, either version 3 of the
  - License, or (at your option) any later version.
  -
  - This program is distributed in the hope that it will be useful,
  - but WITHOUT ANY WARRANTY; without even the implied warranty of
  - MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
  - GNU Affero General Public License for more details.
  -
  - You should have received a copy of the GNU Affero General Public License
  - along with this program. If not, see <http://www.gnu.org/licenses/>.
  -
  -->

<template>
	<div>
		<button class="icon-upload" @click="clickAddNewAttachmment()">
			{{ t('settings', 'Upload attachment') }}
		</button>
		<input ref="localAttachments"
			type="file"
			style="display: none;"
			@change="onLocalAttachmentSelected">
		<div class="attachment-list">
			<ul>
				<li v-for="attachment in attachments"
					:key="attachment.id"
					class="attachment"
					style="display: flex;">
					<a class="fileicon" :style="mimetypeForAttachment(attachment)" :href="attachmentUrl(attachment)" />
					<div class="details">
						<a :href="attachmentUrl(attachment)" target="_blank">
							<div class="filename">
								<span class="basename">{{ attachment.data }}</span>
							</div>
							<span class="filesize">{{ formattedFileSize(attachment.extendedData.filesize) }}</span>
							<span class="filedate">{{ relativeDate(attachment.createdAt*1000) }}</span>
							<span class="filedate">{{ attachment.createdBy }}</span>
						</a>
					</div>
					<Actions>
						<ActionButton v-if="attachment.deletedAt === 0" icon="icon-delete" @click="deleteAttachment(attachment)">
							{{ t('deck', 'Delete Attachment') }}
						</ActionButton>

						<ActionButton v-else icon="icon-history" @click="restoreAttachment(attachment)">
							{{ t('deck', 'Restore Attachment') }}
						</ActionButton>
					</Actions>
				</li>
			</ul>
		</div>

		<Modal v-if="modalShow" :title="t('deck', 'File already exists')" @close="modalShow=false">
			<div class="modal__content">
				<h2>{{ t('deck', 'File already exists') }}</h2>
				<p>
					{{ t('deck', 'A file with the name {filename} already exists.', {filename: file.name}) }}
				</p>
				<p>
					{{ t('deck', 'Do you want to overwrite it?') }}
				</p>
				<button class="primary" @click="overrideAttachment">
					{{ t('deck', 'Overwrite file') }}
				</button>
				<button @click="modalShow=false">
					{{ t('deck', 'Keep existing file') }}
				</button>
			</div>
		</Modal>
	</div>
</template>

<script>
import { Actions, ActionButton, Modal } from '@nextcloud/vue'
import { showError } from '@nextcloud/dialogs'
import { formatFileSize } from '@nextcloud/files'
import relativeDate from '../../mixins/relativeDate'
import { loadState } from '@nextcloud/initial-state'

const maxUploadSizeState = loadState('deck', 'maxUploadSize')

export default {
	name: 'CardSidebarTabAttachments',
	components: {
		Actions,
		ActionButton,
		Modal,
	},
	mixins: [ relativeDate ],
	props: {
		card: {
			type: Object,
			default: null,
		},
	},
	data() {
		return {
			modalShow: false,
			file: '',
			overwriteAttachment: null,
			maxUploadSize: maxUploadSizeState,
		}
	},
	computed: {
		attachments() {
			return this.$store.getters.attachmentsByCard(this.card.id)
		},
		formattedFileSize() {
			return (filesize) => formatFileSize(filesize)
		},
		mimetypeForAttachment() {
			return (attachment) => {
				const url = OC.MimeType.getIconUrl(attachment.extendedData.mimetype)
				const styles = {
					'background-image': `url("${url}")`,
				}
				return styles
			}
		},
		attachmentUrl() {
			return (attachment) => OC.generateUrl(`/apps/deck/cards/${attachment.cardId}/attachment/${attachment.id}`)
		},
	},
	watch: {
		'card': {
			handler() {
			},
		},
	},
	created: function() {
		this.$store.dispatch('fetchAttachments', this.card.id)
	},
	methods: {
		clickAddNewAttachmment() {
			this.$refs.localAttachments.click()
		},
		async onLocalAttachmentSelected(event) {
			const file = event.target.files[0]
			if (this.maxUploadSize > 0 && this.file.size > this.maxUploadSize) {
				showError(
					t('deck', `Failed to upload {name}`, { name: this.file.name }) + ' - '
						+ t('deck', 'Maximum file size of {size} exceeded', { size: formatFileSize(this.maxUploadSize) })
				)
				event.target.value = ''
				return
			}

			const bodyFormData = new FormData()
			bodyFormData.append('cardId', this.card.id)
			bodyFormData.append('type', 'deck_file')
			bodyFormData.append('file', file)

			try {
				await this.$store.dispatch('createAttachment', { cardId: this.card.id, formData: bodyFormData })
			} catch (err) {
				if (err.response.data.status === 409) {
					this.overwriteAttachment = err.response.data.data
					this.modalShow = true
				} else {
					showError(err.response.data.message)
				}
			}
			event.target.value = ''
		},
		deleteAttachment(attachment) {
			this.$store.dispatch('deleteAttachment', attachment)
		},
		restoreAttachment(attachment) {
			this.$store.dispatch('restoreAttachment', attachment)
		},
		overrideAttachment() {
			const bodyFormData = new FormData()
			bodyFormData.append('cardId', this.card.id)
			bodyFormData.append('type', 'deck_file')
			bodyFormData.append('file', this.file)
			this.$store.dispatch('updateAttachment', {
				cardId: this.card.id,
				attachmentId: this.overwriteAttachment.id,
				formData: bodyFormData,
			})

			this.modalShow = false
		},
	},
}
</script>

<style scoped lang="scss">
	.icon-upload {
		padding-left: 35px;
		background-position: 10px center;
	}

	.modal__content {
		width: 25vw;
		min-width: 250px;
		height: 120px;
		text-align: center;
		margin: 20px 20px 60px 20px;
	}

	.modal__content button {
		float: right;
		margin: 40px 3px 3px 0;
	}

	.attachment-list {
		&.selector {
			padding: 10px;
			position: absolute;
			width: 30%;
			max-width: 500px;
			min-width: 200px;
			max-height: 50%;
			top: 50%;
			left: 50%;
			transform: translate(-50%, -50%);
			background-color: #eee;
			z-index: 2;
			border-radius: 3px;
			box-shadow: 0 0 3px darkgray;
			overflow: scroll;
		}
		h3.attachment-selector {
			margin: 0 0 10px;
			padding: 0;
			.icon-close {
				display: inline-block;
				float: right;
			}
		}

		li.attachment {
			display: flex;
			padding: 3px;

			&.deleted {
				opacity: .5;
			}

			.fileicon {
				display: inline-block;
				min-width: 32px;
				width: 32px;
				height: 32px;
				background-size: contain;
			}
			.details {
				flex-grow: 1;
				flex-shrink: 1;
				min-width: 0;
				flex-basis: 50%;
				line-height: 110%;
				padding: 2px;
			}
			.filename {
				width: 70%;
				display: flex;
				.basename {
					white-space: nowrap;
					overflow: hidden;
					text-overflow: ellipsis;
					padding-bottom: 2px;
				}
				.extension {
					opacity: 0.7;
				}
			}
			.filesize, .filedate {
				font-size: 90%;
				color: darkgray;
			}
			.app-popover-menu-utils {
				position: relative;
				right: -10px;
				button {
					height: 32px;
					width: 42px;
				}
			}
			button.icon-history {
				width: 44px;
			}
			progress {
				margin-top: 3px;
			}
		}
	}
</style>
