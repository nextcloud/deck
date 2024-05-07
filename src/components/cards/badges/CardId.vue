<!--
  - SPDX-FileCopyrightText: 2022 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<div v-if="card" class="cardid">
		<span>#{{ card.id }}</span>
	</div>
</template>

<script>

export default {
	name: 'CardId',
	props: {
		card: {
			type: Object,
			default: null,
		},
	},
}
</script>

<style lang="scss" scoped>
	.cardid {
		font-size: .9em;
	}
</style>
