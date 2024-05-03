<!--
  - SPDX-FileCopyrightText: 2018 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<router-view v-if="visible" v-click-outside="onClickOutside" name="sidebar" />
</template>

<script>
export default {
	name: 'Sidebar',
	props: {
		visible: {
			type: Boolean,
			default: true,
		},
	},
	methods: {
		closeSidebar() {
			this.$router.push({ name: 'board' })
		},
		onClickOutside(e) {
			if (e.target?.dataset?.clickClosesSidebar) {
				this.closeSidebar()
			}
		},
	},
}
</script>

<style lang="scss">
	#app-sidebar .icon-close {
		z-index: 100;
	}

	.app-deck .app-sidebar {
		z-index: 1500 !important;
	}
</style>
