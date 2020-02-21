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
		<li v-for="attachment in attachments" :key="attachment.id" class="attachment">
			<a class="fileicon" :style="mimetypeForAttachment(attachment)" />
			<div class="details" :href="attachmentUrl(attachment)">
				<div class="filename">
					<span class="basename">{{ attachment.data }}</span>
				</div>
				<span class="filesize">{{ attachment.extendedData.filesize }}</span>
				<span class="filedate">{{ attachment.createdAt }}</span>
				<span class="filedate">{{ attachment.createdBy }}</span>
			</div>
			<Actions>
				<ActionButton icon="icon-delete" @click="deleteAttachment(attachment)">
					{{ t('deck', 'Delete Attachment') }}
				</ActionButton>
			</Actions>
		</li>
		<button class="icon-upload" @click="clickAddNewAttachmment()">
			{{ t('deck', 'Upload attachment') }}
		</button>
		<input ref="localAttachments"
			type="file"
			style="display: none;"
			@change="onLocalAttachmentSelected">

		<Modal v-if="modalShow" title="File already exists" @close="modalShow=false">
			<div class="modal__content">
				<h2>{{ t('deck', 'File already exists') }}</h2>
				<p>
					{{ t('deck', 'A file with the name') }}
					{{ file.name }}
					{{ t('deck', 'already exists.') }}
				</p>
				<p>
					{{ t('deck', 'Do you want to overwrite it?') }}
				</p>
				<button class="primary" @click="overrideAttachment">
					{{ t('deck', 'Yes') }}
				</button>
				<button @click="modalShow=false">
					{{ t('deck', 'No') }}
				</button>
			</div>
		</Modal>
	</div>
</template>

<script>
import { Actions, ActionButton, Modal } from '@nextcloud/vue'

export default {
	name: 'CardSidebarTabAttachments',
	components: {
		Actions,
		ActionButton,
		Modal,
	},
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
			overrideError: null
		}
	},
	computed: {
		attachments() {
			return this.$store.getters.attachmentsByCard(this.card.id)
		},

	},
	created: function() {
		this.$store.dispatch('fetchAttachments', this.card.id)
	},
	methods: {
		clickAddNewAttachmment() {
			this.$refs.localAttachments.click()
		},
		async onLocalAttachmentSelected(e) {
			const bodyFormData = new FormData()
			bodyFormData.append('cardId', this.card.id)
			bodyFormData.append('type', 'deck_file')
			bodyFormData.append('file', e.target.files[0])
			this.file = e.target.files[0]
			try {
				const data = await this.$store.dispatch('createAttachment', { cardId: this.card.id, formData: bodyFormData })
				console.log(data)
			} catch (e) {
				this.modalShow = true
			}
		},
		deleteAttachment(attachment) {
			this.$store.dispatch('deleteAttachment', attachment)
		},
		mimetypeForAttachment(attachment) {
			const url = OC.MimeType.getIconUrl(attachment.extendedData.mimetype)
			const styles = {
				'background-image': `url("${url}")`,
			}
			return styles
		},
		attachmentUrl(attachment) {
			return OC.generateUrl(`/apps/deck/cards/${attachment.cardId}/attachment/${attachment.id}`)
		},
		overrideAttachment() {
			const bodyFormData = new FormData()
			bodyFormData.append('cardId', this.card.id)
			bodyFormData.append('type', 'deck_file')
			bodyFormData.append('file', this.file)
			this.$store.dispatch('updateAttachment', { cardId: this.card.id, attachmentId: 1, formData: bodyFormData })

			this.modalShow = false
		},
	},
}
</script>

<style scoped>
	.fileicon {
		display: inline-block;
		min-width: 32px;
		width: 32px;
		height: 32px;
		background-size: contain;
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
