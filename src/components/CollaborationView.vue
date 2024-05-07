<!--
  - SPDX-FileCopyrightText: 2019 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<div>
		<CollectionList v-if="boardId"
			:id="boardId"
			:name="boardTitle"
			type="deck" />
	</div>
</template>

<script>
import { CollectionList } from 'nextcloud-vue-collections'

export default {
	name: 'CollaborationView',
	components: {
		CollectionList,
	},
	computed: {
		boardId() {
			if (this.$root.model && this.$root.model.id) {
				return '' + this.$root.model.id
			}
			return null
		},
		boardTitle() {
			if (this.$root.model && this.$root.model.title) {
				return '' + this.$root.model.title
			}
			return ''
		},
	},
}
</script>
