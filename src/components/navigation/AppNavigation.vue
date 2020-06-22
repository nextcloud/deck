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
	<AppNavigationVue :class="{'icon-loading': loading}">
		<ul>
			<AppNavigationBoardCategory
				id="deck-navigation-all"
				to="/board"
				:text="t('deck', 'All boards')"
				:boards="noneArchivedBoards"
				:open-on-add-boards="true"
				icon="icon-deck" />
			<AppNavigationBoardCategory
				id="deck-navigation-archived"
				to="/board/archived"
				:text="t('deck', 'Archived boards')"
				:boards="archivedBoards"
				icon="icon-archive" />
			<AppNavigationBoardCategory
				id="deck-navigation-shared"
				to="/board/shared"
				:text="t('deck', 'Shared with you')"
				:boards="sharedBoards"
				icon="icon-shared" />
			<AppNavigationAddBoard v-if="canCreate" />
		</ul>
		<AppNavigationSettings>
			<div>
				<Multiselect v-model="groupLimit"
					:class="{'icon-loading-small': groupLimitDisabled}"
					open-direction="bottom"
					:options="groups"
					:multiple="true"
					:disabled="groupLimitDisabled"
					:placeholder="t('deck', 'Limit deck usage of groups')"
					label="displayname"
					track-by="id"
					@input="updateConfig" />
				<p>{{ t('deck', 'Limiting Deck will block users not part of those groups from creating their own boards. Users will still be able to work on boards that have been shared with them.') }}</p>
			</div>
		</AppNavigationSettings>
	</AppNavigationVue>
</template>

<script>
import axios from '@nextcloud/axios'
import { mapGetters } from 'vuex'
import ClickOutside from 'vue-click-outside'
import { AppNavigation as AppNavigationVue, AppNavigationSettings, Multiselect } from '@nextcloud/vue'
import AppNavigationAddBoard from './AppNavigationAddBoard'
import AppNavigationBoardCategory from './AppNavigationBoardCategory'
import { loadState } from '@nextcloud/initial-state'
import { generateUrl, generateOcsUrl } from '@nextcloud/router'

const canCreateState = loadState('deck', 'canCreate')

export default {
	name: 'AppNavigation',
	components: {
		AppNavigationVue,
		AppNavigationSettings,
		AppNavigationAddBoard,
		AppNavigationBoardCategory,
		Multiselect,
	},
	directives: {
		ClickOutside,
	},
	props: {
		loading: {
			type: Boolean,
			default: false,
		},
	},
	data() {
		return {
			opened: false,
			groups: [],
			groupLimit: [],
			groupLimitDisabled: true,
			canCreate: canCreateState,
		}
	},
	computed: {
		...mapGetters([
			'noneArchivedBoards',
			'archivedBoards',
			'sharedBoards',
		]),
		isAdmin() {
			// eslint-disable-next-line
			//return oc_isadmin
			return OC.isUserAdmin()
		},
	},
	beforeMount() {
		if (this.isAdmin) {
			axios.get(generateUrl('apps/deck/config')).then((response) => {
				this.groupLimit = response.data.groupLimit
				this.groupLimitDisabled = false
			}, (error) => {
				console.error('Error while loading groupLimit', error.response)
			})
			axios.get(generateOcsUrl('cloud', 2) + 'groups').then((response) => {
				this.groups = response.data.ocs.data.groups.reduce((obj, item) => {
					obj.push({
						id: item,
						displayname: item,
					})
					return obj
				}, [])
			}, (error) => {
				console.error('Error while loading group list', error.response)
			})
		}
	},
	methods: {
		updateConfig() {
			this.groupLimitDisabled = true
			axios.post(generateUrl('apps/deck/config/groupLimit'), {
				value: this.groupLimit,
			}).then(() => {
				this.groupLimitDisabled = false
			}, (error) => {
				console.error('Error while saving groupLimit', error.response)
			})
		},
	},
}
</script>
<style scoped lang="scss">
	#app-settings-content {
		p {
			margin-top: 20px;
			margin-bottom: 20px;
			color: var(--color-text-light);
		}
	}
	::v-deep .app-navigation-toggle {
		display: none;
	}
</style>
