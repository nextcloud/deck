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
			<div class="board-bullet" />
			<a href="#todo">{{ board.title }}</a>
			<span style="display: inline;" class="icon-shared" />
		</div>
		<div v-if="board" class="board-actions">
			<div id="stack-add">
				<form>
					<label for="new-stack-input-main" class="hidden-visually">Add a new stack</label>
					<input id="new-stack-input-main" v-model="newStackTitle" type="text"
						class="no-close"
						placeholder="Add a new stack">
					<input class="icon-confirm" type="button" title="Submit"
						@click="clickAddNewStack()">
				</form>
			</div>
			<div class="board-action-buttons">
				<button title="Show archived cards" class="icon icon-archive" @click="toggleShowArchived" />
				<button :class="[(compactMode ? 'icon-toggle-compact-collapsed' : 'icon-toggle-compact-expanded')]" title="Toggle compact mode" class="icon"
					@click="toggleCompactMode" />
				<router-link v-tooltip="t('deck', 'Board settings')" :to="{name: 'board.details'}" class="icon-settings-dark"
					tag="button" />
			</div>
		</div>
	</div>

</template>

<script>
import { mapState } from 'vuex'
export default {
	name: 'Controls',
	props: {
		board: {
			type: Object,
			required: false,
			default: null
		}
	},
	data() {
		return {
			newStackTitle: '',
			stack: ''
		}
	},
	computed: {
		...mapState({
			compactMode: state => state.compactMode
		})
	},
	methods: {
		toggleNav() {
			this.$store.dispatch('toggleNav')
		},
		toggleSidebar: function() {
			this.$store.dispatch('toggleSidebar')
		},
		toggleCompactMode() {
			this.$store.dispatch('toggleCompactMode')
		},
		toggleShowArchived() {
			this.$store.dispatch('toggleShowArchived')
		},
		clickAddNewStack() {
			this.stack = { title: this.newStackTitle }
			this.$store.dispatch('createStack', this.stack)
			this.newStackTitle = ''
			this.stack = null
		}
	}
}
</script>

<style lang="scss" scoped>
	.controls {
		.crumb {
			order: 0;

			a:nth-child(2),
			a:nth-child(3) {
				padding-left: 0;
				margin-left: -5px;
			}

			a .icon {
				margin-top: 2px;
			}

			.board-bullet {
				display: inline-block;
				width: 20px;
				height: 20px;
				border: none;
				border-radius: 50%;
				background-color: #aaa;
				margin: 12px;
				margin-left: -4px;
			}
		}

		#stack-add form {
			display: flex;
		}
	}

	#app-navigation-toggle-custom {
		width: 44px;
		height: 44px;
		cursor: pointer;
		opacity: 1;
		display: inline-block !important;
		position: fixed;
	}

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

	.board-action-buttons {
		display: flex;
		padding: 3px 4px 7px 4px;
		button {
			border-radius: 0;
			width: 44px;
			margin: 0 0 0 -1px;
		}
		button:first-child {
			border-radius: 3px 0 0 3px;
		}
		button:last-child {
			border-radius: 0 3px 3px 0;
		}
	}

</style>
