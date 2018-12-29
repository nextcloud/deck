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
		<div id="app-navigation-toggle-custom" class="icon-menu" @click="toggleNav" />
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
		<div v-if="board" class="board-actions">
			<div id="stack-add">
				<form>
					<label for="new-stack-input-main" class="hidden-visually">Add a new stack</label>
					<input type="text" class="no-close" id="new-stack-input-main" placeholder="Add a new stack">
					<button class="button-inline icon icon-add" type="submit" title="Submit">
						<span class="hidden-visually">Submit</span>
					</button>
				</form>
			</div>
			<button title="Show archived cards">
				<i class="icon icon-archive"></i>
				<span class="hidden-visually">Show archived cards</span>
			</button>
			<button title="Toggle compact mode">
				<i class="icon icon-toggle-compact-expanded"></i>
				<span class="hidden-visually">Toggle compact mode</span>
			</button>
			<router-link v-tooltip="t('deck', 'Board settings')" :to="{name: 'board.details'}" class="icon-settings"
				tag="button" />
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
		},
		toggleSidebar: function() {
			this.$store.dispatch('toggleSidebar')
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

	.board-actions {
		flex-grow: 1;
		order: 100;
		display: flex;
		justify-content: flex-end;
	}
	button.icon-settings {
		width: 44px;
	}

</style>
