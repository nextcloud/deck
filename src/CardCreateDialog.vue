<!--
  - SPDX-FileCopyrightText: 2021 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<NcModal class="card-selector" @close="close">
		<CreateNewCardCustomPicker :title="title"
			:description="description"
			show-created-notice
			@cancel="close"
			@close="close" />
	</NcModal>
</template>

<script>
import { NcModal } from '@nextcloud/vue'
import CreateNewCardCustomPicker from './views/CreateNewCardCustomPicker.vue'

export default {
	name: 'CardCreateDialog',
	components: {
		NcModal,
		CreateNewCardCustomPicker,
	},
	props: {
		title: {
			type: String,
			default: '',
		},
		description: {
			type: String,
			default: '',
		},
	},
}
</script>
