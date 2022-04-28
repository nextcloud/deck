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
		<template #list>
			<AppNavigationItem
				:title="t('deck', 'Upcoming cards')"
				icon="icon-calendar-dark"
				:exact="true"
				to="/" />
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
		</template>
		<template #footer>
			<AppNavigationSettings>
				<div>
					<div class="value-unit">
						<label for="set-value-unit">
							{{ t('deck', 'Set value unit') }}
						</label>
						<input id="set-value-unit"
							v-model="configValueUnit"
							type="text">
					</div>

					<div>
						<input id="toggle-modal"
							v-model="cardDetailsInModal"
							type="checkbox"
							class="checkbox">
						<label for="toggle-modal">
							{{ t('deck', 'Use bigger card view') }}
						</label>
					</div>

					<div>
						<input id="toggle-calendar"
							v-model="configCalendar"
							type="checkbox"
							class="checkbox">
						<label for="toggle-calendar">
							{{ t('deck', 'Show boards in calendar/tasks') }}
						</label>
					</div>

					<Multiselect v-if="isAdmin"
						v-model="groupLimit"
						:class="{'icon-loading-small': groupLimitDisabled}"
						open-direction="bottom"
						:options="groups"
						:multiple="true"
						:disabled="groupLimitDisabled"
						:placeholder="t('deck', 'Limit deck usage of groups')"
						label="displayname"
						track-by="id"
						@input="updateConfig" />
					<p v-if="isAdmin">
						{{ t('deck', 'Limiting Deck will block users not part of those groups from creating their own boards. Users will still be able to work on boards that have been shared with them.') }}
					</p>
				</div>
			</AppNavigationSettings>
		</template>
	</AppNavigationVue>
</template>

<script>
import axios from '@nextcloud/axios'
import { mapGetters } from 'vuex'
import ClickOutside from 'vue-click-outside'
import { AppNavigation as AppNavigationVue, AppNavigationItem, AppNavigationSettings, Multiselect } from '@nextcloud/vue'
import AppNavigationAddBoard from './AppNavigationAddBoard'
import AppNavigationBoardCategory from './AppNavigationBoardCategory'
import { loadState } from '@nextcloud/initial-state'
import { generateOcsUrl } from '@nextcloud/router'
import { getCurrentUser } from '@nextcloud/auth'

const canCreateState = loadState('deck', 'canCreate')

export default {
	name: 'AppNavigation',
	components: {
		AppNavigationVue,
		AppNavigationSettings,
		AppNavigationAddBoard,
		AppNavigationBoardCategory,
		Multiselect,
		AppNavigationItem,
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
			return !!getCurrentUser()?.isAdmin
		},
		cardDetailsInModal: {
			get() {
				return this.$store.getters.config('cardDetailsInModal')
			},
			set(newValue) {
				this.$store.dispatch('setConfig', { cardDetailsInModal: newValue })
			},
		},
		configCalendar: {
			get() {
				return this.$store.getters.config('calendar')
			},
			set(newValue) {
				this.$store.dispatch('setConfig', { calendar: newValue })
			},
		},
		configValueUnit: {
			get() {
				return this.$store.getters.config('valueUnit')
			},
			set(newValue) {
				this.$store.dispatch('setConfig', { valueUnit: newValue })
			},
		},
	},
	beforeMount() {
		if (this.isAdmin) {
			this.groupLimit = this.$store.getters.config('groupLimit')
			this.groupLimitDisabled = false
			axios.get(generateOcsUrl('cloud/groups')).then((response) => {
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
		async updateConfig() {
			await this.$store.dispatch('setConfig', { groupLimit: this.groupLimit })
			this.groupLimitDisabled = false
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

	.value-unit {
		display: flex;
		align-items: center;
		justify-content: space-between;
	}
</style>
