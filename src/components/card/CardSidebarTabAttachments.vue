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
	<AttachmentDragAndDrop :card-id="card.id" class="drop-upload--sidebar">
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

				<AttachmentList
					:card-id="card.id"
					:removable="true"
					@deleteAttachment="deleteAttachment"
					@restoreAttachment="restoreAttachment" />
			</ul>
		</div>
	</AttachmentDragAndDrop>
</template>

<script>
import { mapState } from 'vuex'
import { loadState } from '@nextcloud/initial-state'
import AttachmentDragAndDrop from '../AttachmentDragAndDrop'
import attachmentUpload from '../../mixins/attachmentUpload'
import AttachmentList from './AttachmentList'
const maxUploadSizeState = loadState('deck', 'maxUploadSize')

export default {
	name: 'CardSidebarTabAttachments',
	components: {
		AttachmentDragAndDrop,
		AttachmentList,
	},
	mixins: [ attachmentUpload ],
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
			isDraggingOver: false,
			maxUploadSize: maxUploadSizeState,
		}
	},
	computed: {
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
		attachments() {
			return [...this.$store.getters.attachmentsByCard(this.card.id)].sort((a, b) => b.id - a.id)
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
		cardId() {
			return this.card.id
		},
	},
	watch: {
		card(newCard) {
			this.$store.dispatch('fetchAttachments', newCard.id)
		},
	},
	created: function() {
		this.$store.dispatch('fetchAttachments', this.card.id)
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
		deleteAttachment(attachment) {
			this.$store.dispatch('deleteAttachment', attachment)
		},
		restoreAttachment(attachment) {
			this.$store.dispatch('restoreAttachment', attachment)
		},
	},
}
</script>

<style scoped lang="scss">
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
