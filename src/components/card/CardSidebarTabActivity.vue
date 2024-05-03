<!--
  - SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<ActivityList v-if="$parent.isActive"
		:key="card.id"
		filter="filter"
		:object-id="card.id"
		object-type="deck_card"
		type="deck" />
</template>

<script>
import ActivityList from '../ActivityList.vue'

export default {
	name: 'CardSidebarTabActivity',
	components: {
		ActivityList,
	},
	props: {
		card: {
			type: Object,
			default: null,
		},
	},
	data() {
		return {
			id: 'activity',
		}
	},
}
</script>
