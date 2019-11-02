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
	<li v-if="boards.length > 0" :id="id"
		:class="{'open': opened, 'collapsible': collapsible }">
		<button v-if="collapsible" class="collapse" @click.prevent.stop="toggleCollapse" />
		<a :class="icon" href="#">
			{{ text }}
		</a>
		<ul class="list">
			<router-link v-for="item in items" id="board-1"

				:key="item.title" :title="item.title" :to="{name: item.to}"
				tag="li" class="list-item">
				<div :class="item.icon" style="padding: 0 12px 0 44px" />{{ item.title }}
			</router-link>
		</ul>
	</li>
</template>

<script>
import ClickOutside from 'vue-click-outside'

export default {
	name: 'AppNavigationCollection',
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
			opened: false,
			items: [
				{ title: t('deck', 'Due soon') + ' (5)', icon: 'icon-calendar-dark', to: 'collections.due' },
				{ title: t('deck', 'Assigned to me') + ' (9)', icon: 'icon-user', to: 'collections.my' },
				{ title: t('deck', 'All cards'), icon: 'icon-projects', to: 'collections' }
			]
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
<style>
	.list {
		opacity: 0.57;
	}
	.list-item {
		padding: 10px;
	}
</style>
