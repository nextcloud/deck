<!--
* @copyright Copyright (c) 2018 Michael Weimann <mail@michael-weimann.eu>
*
* @author Michael Weimann <mail@michael-weimann.eu>
*
* @license GNU AGPL version 3 or any later version
*
* This program is free software: you can redistribute it and/or modify
* it under the terms of the GNU Affero General Public License as
* published by the Free Software Foundation, either version 3 of the
* License, or (at your option) any later version.
*
* This program is distributed in the hope that it will be useful,
* but WITHOUT ANY WARRANTY; without even the implied warranty of
* MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
* GNU Affero General Public License for more details.
*
* You should have received a copy of the GNU Affero General Public License
* along with this program. If not, see <http://www.gnu.org/licenses/>.
*
-->

<template>

	<div class="controls">
		<div id="app-navigation-toggle-custom" class="icon-menu" v-on:click="toggleNav" />
		<div class="breadcrumb">
			<div class="crumb svg last">
				<router-link to="/boards" class="icon-home" title="All Boards">
					<span class="hidden-visually">All Boards</span>
				</router-link>
			</div>
		</div>
		<div v-if="board" class="crumb svg">
			<a href="#todo">{{ board.title }}</a>
			<span style="display: inline;" class="icon-shared" />
		</div>
	</div>

</template>

<script>
export default {
	name: 'Controls',
	props: {
		board: {
			type: Object,
			required: false,
			default: null
		}
	},
	methods: {
		toggleNav() {
			this.$store.dispatch('toggleNav')
		}
	}
}
</script>

<style lang="scss" scoped>

	.controls {
		display: flex;
	}

	#app-navigation-toggle-custom {
		position: static;
	}

</style>
