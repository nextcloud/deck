<!--
  - SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<AttachmentList :card-id="card.id"
		:removable="true"
		@delete-attachment="deleteAttachment"
		@restore-attachment="restoreAttachment" />
</template>

<script>
import { mapActions } from 'pinia'
import AttachmentList from './AttachmentList.vue'
import { useAttachmentStore } from '../../stores/attachment.js'
export default {
	name: 'CardSidebarTabAttachments',
	components: {
		AttachmentList,
	},
	props: {
		card: {
			type: Object,
			default: null,
		},
	},
	methods: {
		...mapActions(useAttachmentStore, [
			'deleteAttachment',
			'restoreAttachment',
		]),
	},
}
</script>
