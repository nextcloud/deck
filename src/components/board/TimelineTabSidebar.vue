<!--
  - SPDX-FileCopyrightText: 2019 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<ActivityList v-if="isActive"
		filter="deck"
		:object-id="board.id"
		object-type="deck"
		type="deck" />
</template>

<script>
import ActivityList from '../ActivityList.vue'

export default {
	name: 'TimelineTabSidebar',
	components: {
		ActivityList,
	},
	props: {
		board: {
			type: Object,
			default: undefined,
		},
		isActive: {
			type: Boolean,
			default: false,
		},
	},
}
</script>
