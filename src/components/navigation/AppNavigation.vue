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
		<div v-click-outside="closeMenu" v-if="isAdmin"
			id="app-settings" :class="{open: opened}">
			<div id="app-settings-header">
				<button class="settings-button" @click="toggleMenu">
					{{ t('deck', 'Settings') }}
				</button>
			</div>
			<div id="app-settings-content">
				<Multiselect :class="{'icon-loading-small': groupLimitDisabled}" :options="groups" :multiple="true"
					v-model="groupLimit"
					:disabled="groupLimitDisabled" label="displayname" track-by="id"
					@input="updateConfig" />
				<p>{{ t('deck', 'Limiting Deck will block users not part of those groups from creating their own boards. Users will still be able to work on boards that have been shared with them.') }}</p>
			</div>
		</div>
	</div>
</template>

<script>
import axios from 'nextcloud-axios'
import { mapGetters } from 'vuex'
import ClickOutside from 'vue-click-outside'
import { Multiselect } from '@nextcloud/vue/dist/Components/Multiselect'

import AppNavigationAddBoard from './AppNavigationAddBoard'
import AppNavigationBoard from './AppNavigationBoard'
import AppNavigationBoardCategory from './AppNavigationBoardCategory'

export default {
	name: 'AppNavigation',
	components: {
		AppNavigationAddBoard,
		AppNavigationBoard,
		AppNavigationBoardCategory,
		Multiselect
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
			opened: false,
			groups: [],
			groupLimit: [],
			groupLimitDisabled: true
		}
	},
	computed: {
		...mapGetters([
			'noneArchivedBoards',
			'archivedBoards',
			'sharedBoards'
		]),
		isAdmin() {
			// eslint-disable-next-line
			//return oc_isadmin
			return OC.isUserAdmin()
		}
	},
	beforeMount() {
		if (this.isAdmin) {
			axios.get(OC.generateUrl('apps/deck/config')).then((response) => {
				this.groupLimit = response.data.groupLimit
				this.groupLimitDisabled = false
			}, (error) => {
				console.error('Error while loading groupLimit', error.response)
			})
			axios.get(OC.linkToOCS('cloud', 2) + 'groups').then((response) => {
				this.groups = response.data.ocs.data.groups.reduce((obj, item) => {
					obj.push({
						id: item,
						displayname: item
					})
					return obj
				}, [])
			}, (error) => {
				console.error('Error while loading group list', error.response)
			})
		}
	},
	methods: {
		toggleMenu() {
			this.opened = !this.opened
		},
		closeMenu() {
			this.opened = false
		},
		updateConfig() {
			this.groupLimitDisabled = true
			axios.post(OC.generateUrl('apps/deck/config/groupLimit'), {
				value: this.groupLimit
			}).then(() => {
				this.groupLimitDisabled = false
			}, (error) => {
				console.error('Error while saving groupLimit', error.response)
			})
		}
	}
}
</script>
<style>
	.multiselect {
		width: 100%;
	}
</style>
