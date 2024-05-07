<!--
  - SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<div class="selector-wrapper" :aria-label="label">
		<div class="selector-wrapper--icon">
			<slot name="icon" />
		</div>
		<div class="selector-wrapper--content">
			<slot />
		</div>
	</div>
</template>

<script>
import { defineComponent } from 'vue'

export default defineComponent({
	name: 'CardDetailEntry',
	props: {
		label: {
			type: String,
			required: true,
		},
	},
})
</script>
<style lang="scss">
@import '../../css/selector';
</style>
