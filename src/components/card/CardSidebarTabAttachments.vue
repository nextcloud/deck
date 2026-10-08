<!--
  - SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<AttachmentList
		:cardId="card.id"
		:removable="true"
		@deleteAttachment="deleteAttachment"
		@restoreAttachment="restoreAttachment" />
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
