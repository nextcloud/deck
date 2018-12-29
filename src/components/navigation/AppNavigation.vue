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
	<div id="app-navigation" :class="{'icon-loading': loading}">
		<ul id="deck-navigation">
			<app-navigation-board-category
				id="deck-navigation-all"
				:text="t('deck', 'All boards')"
				:boards="noneArchivedBoards"
				:open-on-add-boards="true"
				icon="icon-deck" />
			<app-navigation-board-category
				id="deck-navigation-archived"
				:text="t('deck', 'Archived boards')"
				:boards="archivedBoards"
				icon="icon-archive" />
			<app-navigation-board-category
				id="deck-navigation-shared"
				:text="t('deck', 'Shared boards')"
				:boards="sharedBoards"
				icon="icon-shared" />
			<app-navigation-add-board />
		</ul>
		<div v-click-outside="closeMenu" v-if="!!$slots['settings-content']" id="app-settings"
			:class="{open: opened}">
			<div id="app-settings-header">
				<button class="settings-button"
					data-apps-slide-toggle="#app-settings-content"
					@click="toggleMenu">
					{{ t('contacts', 'Settings') }}
				</button>
			</div>
			<div id="app-settings-content">
				<slot name="settings-content" />
			</div>
		</div>
	</div>
</template>

<script>
import { mapGetters } from 'vuex'
import ClickOutside from 'vue-click-outside'

import AppNavigationAddBoard from './AppNavigationAddBoard'
import AppNavigationBoard from './AppNavigationBoard'
import AppNavigationBoardCategory from './AppNavigationBoardCategory'

export default {
	name: 'AppNavigation',
	components: {
		AppNavigationAddBoard,
		AppNavigationBoard,
		AppNavigationBoardCategory
	},
	directives: {
		ClickOutside
	},
	props: {
		loading: {
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
		...mapGetters([
			'noneArchivedBoards',
			'archivedBoards',
			'sharedBoards'
		])
	},
	methods: {
		toggleMenu() {
			this.opened = !this.opened
		},
		closeMenu() {
			this.opened = false
		}
	}
}
</script>
