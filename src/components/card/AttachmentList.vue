<!--
  - SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<AttachmentDragAndDrop :card-id="cardId" class="drop-upload--sidebar">
		<div v-if="!isReadOnly" class="button-group">
			<NcButton class="icon-upload" @click="uploadNewFile()">
				{{ t('deck', 'Upload new files') }}
			</NcButton>
			<NcButton class="icon-folder" @click="shareFromFiles()">
				{{ t('deck', 'Share from Files') }}
			</NcButton>
		</div>
		<input ref="filesAttachment"
			type="file"
			style="display: none;"
			multiple
			@change="handleUploadFile">
		<ul class="attachment-list">
			<li v-for="attachment in uploadQueue" :key="attachment.name" class="attachment">
				<a class="fileicon" :style="mimetypeForAttachment()" />
				<div class="details">
					<a>
						<div class="filename">
							<span class="basename">{{ attachment.name }}</span>
						</div>
						<progress :value="attachment.progress" max="100" />
					</a>
				</div>
			</li>
			<li v-for="attachment in attachments"
				:key="attachment.id"
				class="attachment"
				:class="{ 'attachment--deleted': attachment.deletedAt > 0 }">
				<a class="fileicon"
					:href="internalLink(attachment)"
					:style="mimetypeForAttachment(attachment)"
					@click.prevent="showViewer(attachment)" />
				<div class="details">
					<a :href="internalLink(attachment)" @click.prevent="showViewer(attachment)">
						<div class="filename">
							<span class="basename">{{ attachment.data }}</span>
						</div>
						<div v-if="attachment.deletedAt === 0">
							<span class="filesize">{{ formattedFileSize(attachment.extendedData.filesize) }}</span>
							<span class="filedate">{{ relativeDate(attachment.createdAt*1000) }}</span>
							<span class="filedate">{{ attachment.extendedData.attachmentCreator.displayName }}</span>
						</div>
						<div v-else>
							<span class="attachment--info">{{ t('deck', 'Pending share') }}</span>
						</div>
					</a>
				</div>
				<NcActions v-if="selectable">
					<NcActionButton icon="icon-confirm" @click="$emit('select-attachment', attachment)">
						{{ t('deck', 'Add this attachment') }}
					</NcActionButton>
				</NcActions>
				<NcActions v-if="removable && !isReadOnly" :force-menu="true">
					<NcActionLink v-if="attachment.extendedData.fileid" icon="icon-folder" :href="internalLink(attachment)">
						{{ t('deck', 'Show in Files') }}
					</NcActionLink>
					<NcActionLink v-if="attachment.extendedData.fileid"
						icon="icon-download"
						:href="downloadLink(attachment)"
						download>
						{{ t('deck', 'Download') }}
					</NcActionLink>
					<NcActionButton v-if="attachment.extendedData.fileid && !isReadOnly" icon="icon-delete" @click="unshareAttachment(attachment)">
						{{ t('deck', 'Remove attachment') }}
					</NcActionButton>

					<NcActionButton v-if="!attachment.extendedData.fileid && attachment.deletedAt === 0" icon="icon-delete" @click="$emit('delete-attachment', attachment)">
						{{ t('deck', 'Delete Attachment') }}
					</NcActionButton>
					<NcActionButton v-else-if="!attachment.extendedData.fileid" icon="icon-history" @click="$emit('restore-attachment', attachment)">
						{{ t('deck', 'Restore Attachment') }}
					</NcActionButton>
				</NcActions>
			</li>
		</ul>
	</AttachmentDragAndDrop>
</template>

<script>
import axios from '@nextcloud/axios'
import { NcActions, NcActionButton, NcActionLink, NcButton } from '@nextcloud/vue'
import AttachmentDragAndDrop from '../AttachmentDragAndDrop.vue'
import relativeDate from '../../mixins/relativeDate.js'
import { formatFileSize } from '@nextcloud/files'
import { getCurrentUser } from '@nextcloud/auth'
import { generateUrl, generateOcsUrl, generateRemoteUrl } from '@nextcloud/router'
import { mapState, mapActions } from 'vuex'
import { loadState } from '@nextcloud/initial-state'
import attachmentUpload from '../../mixins/attachmentUpload.js'
import { getFilePickerBuilder } from '@nextcloud/dialogs'
const maxUploadSizeState = loadState('deck', 'maxUploadSize', -1)

const picker = getFilePickerBuilder(t('deck', 'File to share'))
	.setMultiSelect(false)
	.setType(1)
	.allowDirectories()
	.build()

export default {
	name: 'AttachmentList',
	components: {
		NcActions,
		NcActionButton,
		NcActionLink,
		NcButton,
		AttachmentDragAndDrop,
	},
	mixins: [relativeDate, attachmentUpload],

	props: {
		cardId: {
			type: Number,
			required: true,
		},
		selectable: {
			type: Boolean,
			required: false,
		},
		removable: {
			type: Boolean,
			required: false,
		},
	},
	data() {
		return {
			modalShow: false,
			file: '',
			overwriteAttachment: null,
			isDraggingOver: false,
			maxUploadSize: maxUploadSizeState,
		}
	},
	computed: {
		attachments() {
			// FIXME sort propertly by last modified / deleted at
			return [...this.$store.getters.attachmentsByCard(this.cardId)].filter(attachment => attachment.deletedAt >= 0).sort((a, b) => b.id - a.id)
		},
		mimetypeForAttachment() {
			return (attachment) => {
				if (!attachment) {
					return {}
				}
				const url = attachment.extendedData.hasPreview ? this.attachmentPreview(attachment) : OC.MimeType.getIconUrl(attachment.extendedData.mimetype)
				const styles = {
					'background-image': `url("${url}")`,
				}
				return styles
			}
		},
		attachmentPreview() {
			return (attachment) => (attachment.extendedData.fileid ? generateUrl(`/core/preview?fileId=${attachment.extendedData.fileid}&x=64&y=64`) : null)
		},
		attachmentUrl() {
			return (attachment) => generateUrl(`/apps/deck/cards/${attachment.cardId}/attachment/${attachment.id}`)
		},
		internalLink() {
			return (attachment) => generateUrl('/f/' + attachment.extendedData.fileid)
		},
		downloadLink() {
			return (attachment) => generateRemoteUrl(`dav/files/${getCurrentUser().uid}/${attachment.extendedData.path}`)
		},
		formattedFileSize() {
			return (filesize) => formatFileSize(filesize)
		},
		...mapState({
			currentBoard: state => state.currentBoard,
		}),
		isReadOnly() {
			return !this.$store.getters.canEdit
		},
		dropHintText() {
			if (this.isReadOnly) {
				return t('deck', 'This board is read only')
			} else {
				return t('deck', 'Drop your files to upload')
			}
		},
	},
	watch: {
		cardId: {
			immediate: true,
			handler() {
				this.fetchAttachments(this.cardId)
			},
		},
	},
	methods: {
		...mapActions([
			'fetchAttachments',
		]),
		handleUploadFile(event) {
			const files = event.target.files ?? []
			for (const file of files) {
				this.onLocalAttachmentSelected(file, 'file')
			}
			event.target.value = ''
		},
		uploadNewFile() {
			this.$refs.filesAttachment.click()
		},
		shareFromFiles() {
			picker.pick()
				.then(async (path) => {
					console.debug(`path ${path} selected for sharing`)
					if (!path.startsWith('/')) {
						throw new Error(t('files', 'Invalid path selected'))
					}

					axios.post(generateOcsUrl('apps/files_sharing/api/v1/shares'), {
						path,
						shareType: 12,
						shareWith: '' + this.cardId,
					}).then(() => {
						this.fetchAttachments(this.cardId)
					})
				})
		},
		unshareAttachment(attachment) {
			this.$store.dispatch('unshareAttachment', attachment)
		},
		clickAddNewAttachmment() {
			this.$refs.localAttachments.click()
		},
		showViewer(attachment) {
			if (attachment.extendedData.fileid && window.OCA.Viewer.availableHandlers.map(handler => handler.mimes).flat().includes(attachment.extendedData.mimetype)) {
				window.OCA.Viewer.open({ path: attachment.extendedData.path })
				return
			}

			if (attachment.extendedData.fileid) {
				window.location = generateUrl('/f/' + attachment.extendedData.fileid)
				return
			}

			window.location = generateUrl(`/apps/deck/cards/${attachment.cardId}/attachment/${attachment.id}`)
		},
	},
}
</script>

<style lang="scss" scoped>

	.drop-upload--sidebar {
		min-height: 100%;
	}

	.button-group {
		display: flex;
		gap: calc(var(--default-grid-baseline) * 3);

		.icon-upload, .icon-folder {
			padding-left: var(--default-clickable-area);
			background-position: 16px center;
			flex-grow: 1;
			height: var(--default-clickable-area);
			margin-bottom: 12px;
			text-align: left;
		}
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
			min-height: var(--default-clickable-area);

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
			.attachment--info,
			.filesize, .filedate {
				font-size: 90%;
				color: var(--color-text-maxcontrast);
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
				width: var(--default-clickable-area);
			}
			progress {
				margin-top: 3px;
			}
		}
	}

</style>
