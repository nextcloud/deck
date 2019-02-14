<!--
  - @copyright Copyright (c) 2018 Julius Härtl <jus@bitgrid.net>
  -
  - @author Julius Härtl <jus@bitgrid.net>
  -
  - @license GNU AGPL version 3 or any later version
  -
  - This program is free software: you can redistribute it and/or modify
  - it under the terms of the GNU Affero General Public License as
  - published by the Free Software Foundation, either version 3 of the
  - License, or (at your option) any later version.
  -
  - This program is distributed in the hope that it will be useful,
  - but WITHOUT ANY WARRANTY; without even the implied warranty of
  - MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
  - GNU Affero General Public License for more details.
  -
  - You should have received a copy of the GNU Affero General Public License
  - along with this program. If not, see <http://www.gnu.org/licenses/>.
  -
  -->

<template>
	<div id="app-sidebar">
		<span class="icon-close" title="Close" @click="closeSidebar">
			<span class="hidden-visually">Close</span>
		</span>
		<router-view name="sidebar" />
	</div>

</template>

<script>
export default {
	name: 'Sidebar',
	methods: {
		closeSidebar() {
			this.$router.push({ name: 'board' })
		}
	}
}
</script>

<style lang="scss" scoped>
	.icon-close {
		top: 0;
		width: 44px;
		height: 44px;
		display: inline-block;
		position: absolute;
		right: 0;
	}
</style>
