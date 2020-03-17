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
  - MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
  - GNU Affero General Public License for more details.
  -
  - You should have received a copy of the GNU Affero General Public License
  - along with this program. If not, see <http://www.gnu.org/licenses/>.
  -
  -->

<template>
	<div class="attachments-drag-zone"
		@dragover.prevent="!isDraggingOver && (isDraggingOver = true)"
		@dragleave.prevent="isDraggingOver && (isDraggingOver = false)"
		@drop.prevent="handleDropFiles">
		<slot />
		<transition name="fade" mode="out-in">
			<div
				v-show="isDraggingOver"
				class="dragover">
				<div class="drop-hint">
					<div
						class="drop-hint__icon"
						:class="{
							'icon-upload' : !isReadOnly,
							'icon-error' : isReadOnly}" />
					<h2
						class="drop-hint__text">
						{{ dropHintText }}
					</h2>
				</div>
			</div>
		</transition>

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
import { Modal } from '@nextcloud/vue'
import attachmentUpload from '../mixins/attachmentUpload'
import { loadState } from '@nextcloud/initial-state'
const maxUploadSizeState = loadState('deck', 'maxUploadSize')

export default {
	name: 'AttachmentDragAndDrop',
	components: { Modal },
	mixins: [ attachmentUpload ],
	props: {
		cardId: {
			type: Number,
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
	methods: {
		dragEnter() {

		},
		dragLeave() {

		},
		handleDropFiles(event) {
			this.isDraggingOver = false
			if (this.isReadOnly) {
				return
			}
			this.onLocalAttachmentSelected(event.dataTransfer.files[0])
			event.dataTransfer.value = ''
		},
	},
}
</script>

<style scoped lang="scss">

	.attachments-drag-zone {
		flex-grow: 1;
	}

	.dragover {
		position: absolute;
		top: 20%;
		left: 10%;
		width: 80%;
		height: 60%;
		background: var(--color-primary-light);
		z-index: 11;
		display: flex;
		box-shadow: 0px 0px 36px var(--color-box-shadow);
		border-radius: var(--border-radius);
		opacity: .9;
	}

	.drop-hint {
		margin: auto;
		&__icon {
			background-size: 48px;
			height: 48px;
			margin-bottom: 16px;
		}
	}

	.fade {
		&-enter {
			opacity: 0;
		}
		&-enter-to {
			opacity: .9;
		}
		&-leave {
			opacity: .9;
		}
		&-leave-to {
			opacity: 0;
		}
		&-enter-active,
		&-leave-active {
			transition: opacity 150ms ease-in-out;
		}
	}

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
</style>
