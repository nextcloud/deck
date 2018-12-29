<!--
  - @copyright Copyright (c) 2018 John Molakvoæ <skjnldsv@protonmail.com>
  -
  - @author John Molakvoæ <skjnldsv@protonmail.com>
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
  - MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
  - GNU Affero General Public License for more details.
  -
  - You should have received a copy of the GNU Affero General Public License
  - along with this program. If not, see <http://www.gnu.org/licenses/>.
  -
  -->
<template>
	<li :id="id" :title="text" :class="{'open': opened, 'collapsible': collapsible }">
		<button v-if="collapsible" class="collapse" @click.prevent.stop="toggleCollapse" />
		<a :class="icon" href="#">
			{{ text }}
		</a>
		<ul v-if="boards.length > 0">
			<app-navigation-board v-for="board in boards" :key="board.id" :board="board" />
		</ul>
	</li>
</template>

<script>
import { PopoverMenu } from 'nextcloud-vue'
import ClickOutside from 'vue-click-outside'
import AppNavigationBoard from './AppNavigationBoard'

export default {
	name: 'AppNavigationBoardCategory',
	components: {
		AppNavigationBoard,
		PopoverMenu
	},
	directives: {
		ClickOutside
	},
	props: {
		id: {
			type: String,
			required: true
		},
		text: {
			type: String,
			required: true
		},
		icon: {
			type: String,
			required: true
		},
		boards: {
			type: Array,
			required: true
		},
		/**
		 * Control whether the category should be opened when adding boards.
		 * This is for example used in the case a new board has been added, so the user directly sees it.
		 */
		openOnAddBoards: {
			type: Boolean,
			default: false
		}
	},
	data() {
		return {
			opened: false
		}
	},
	computed: {
		collapsible() {
			return this.boards.length > 0
		}
	},
	watch: {
		boards: function(newVal, prevVal) {
			if (this.openOnAddBoards === true && prevVal.length < newVal.length) {
				this.opened = true
			}
		}
	},
	mounted() {},
	methods: {
		toggleCollapse() {
			this.opened = !this.opened
		}
	}
}
</script>
