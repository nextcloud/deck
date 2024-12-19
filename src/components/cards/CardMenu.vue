<!--
  - SPDX-FileCopyrightText: 2018 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<div v-if="card" class="card-menu" @click.stop.prevent>
		<NcActions>
			<CardMenuEntries :card="card" @edit-title="(id) => $emit('edit-title', id)" />
		</NcActions>
	</div>
</template>
<script>
import { NcActions } from '@nextcloud/vue'
import CardMenuEntries from './CardMenuEntries.vue'

export default {
	name: 'CardMenu',
	components: { NcActions, CardMenuEntries },
	props: {
		card: {
			type: Object,
			default: null,
		},
	},
	emits: ['edit-title'],
}
</script>
