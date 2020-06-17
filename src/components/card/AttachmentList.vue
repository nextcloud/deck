<!--
  - @copyright Copyright (c) 2020 Jakob Röhrl <jakob.roehrl@web.de>
  -
  - @author Jakob Röhrl <jakob.roehrl@web.de>
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
  - MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
  - GNU Affero General Public License for more details.
  -
  - You should have received a copy of the GNU Affero General Public License
  - along with this program. If not, see <http://www.gnu.org/licenses/>.
  -
  -->

<template>
	<AttachmentDragAndDrop :card-id="cardId" class="drop-upload--sidebar">
		<button class="icon-upload" @click="clickAddNewAttachmment()">
			{{ t('deck', 'Upload attachment') }}
		</button>
		<input ref="localAttachments"
			type="file"
			style="display: none;"
			multiple
			@change="handleUploadFile">
		<div class="attachment-list">
			<ul>
				<li v-for="attachment in uploadQueue" :key="attachment.name" class="attachment">
					<a class="fileicon" :style="mimetypeForAttachment('none')" />
					<div class="details">
						<a>
							<div class="filename">
								<span class="basename">{{ attachment.name }}</span>
							</div>
							<progress :value="attachment.progress" max="100" />
						</a>
					</div>
				</li>

				<div class="attachment-list">
					<ul>
						<li v-for="attachment in attachments"
							:key="attachment.id"
							class="attachment">
							<a class="fileicon" :style="mimetypeForAttachment(attachment.extendedData.mimetype)" :href="attachmentUrl(attachment)" />
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
							<Actions v-if="selectable">
								<ActionButton icon="icon-confirm" @click="$emit('selectAttachment', attachment)">
									{{ t('deck', 'Add this attachment') }}
								</ActionButton>
							</Actions>
							<Actions v-if="removable">
								<ActionButton v-if="attachment.deletedAt === 0" icon="icon-delete" @click="$emit('deleteAttachment', attachment)">
									{{ t('deck', 'Delete Attachment') }}
								</ActionButton>

								<ActionButton v-else icon="icon-history" @click="$emit('restoreAttachment', attachment)">
									{{ t('deck', 'Restore Attachment') }}
								</ActionButton>
							</Actions>
						</li>
					</ul>
				</div>
			</ul>
		</div>
	</AttachmentDragAndDrop>
</template>

<script>
import { Actions, ActionButton } from '@nextcloud/vue'
import AttachmentDragAndDrop from '../AttachmentDragAndDrop'
import relativeDate from '../../mixins/relativeDate'
import { formatFileSize } from '@nextcloud/files'
import { generateUrl } from '@nextcloud/router'
import { mapState } from 'vuex'
import { loadState } from '@nextcloud/initial-state'
import attachmentUpload from '../../mixins/attachmentUpload'
const maxUploadSizeState = loadState('deck', 'maxUploadSize')

export default {
	name: 'AttachmentList',
	components: {
		Actions,
		ActionButton,
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
			return [...this.$store.getters.attachmentsByCard(this.cardId)].sort((a, b) => b.id - a.id)
		},
		mimetypeForAttachment() {
			return (mimetype) => {
				const url = OC.MimeType.getIconUrl(mimetype)
				const styles = {
					'background-image': `url("${url}")`,
				}
				return styles
			}
		},
		attachmentUrl() {
			return (attachment) => generateUrl(`/apps/deck/cards/${attachment.cardId}/attachment/${attachment.id}`)
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
	created() {
		this.$store.dispatch('fetchAttachments', this.cardId)
	},
	methods: {
		handleUploadFile(event) {
			const files = event.target.files ?? []
			for (const file of files) {
				this.onLocalAttachmentSelected(file)
			}
			event.target.value = ''
		},
		clickAddNewAttachmment() {
			this.$refs.localAttachments.click()
		},
	},
}
</script>

<style lang="scss" scoped>

	.icon-upload {
		padding-left: 35px;
		background-position: 10px center;
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
			min-height: 44px;

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
