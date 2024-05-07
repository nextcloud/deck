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
import AttachmentList from './AttachmentList.vue'
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
		deleteAttachment(attachment) {
			this.$store.dispatch('deleteAttachment', attachment)
		},
		restoreAttachment(attachment) {
			this.$store.dispatch('restoreAttachment', attachment)
		},
	},
}
</script>
