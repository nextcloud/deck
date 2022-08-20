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
	<NcAppNavigation :class="{'icon-loading': loading}">
		<template #list>
			<NcAppNavigationItem :title="t('deck', 'Upcoming cards')"
				:exact="true"
				to="/">
				<template #icon>
					<CalendarIcon :size="20" />
				</template>
			</NcAppNavigationItem>
			<AppNavigationBoardCategory id="deck-navigation-all"
				to="/board"
				:text="t('deck', 'All boards')"
				:boards="noneArchivedBoards"
				:open-on-add-boards="true"
				icon="icon-deck">
				<template #icon>
					<DeckIcon :size="16" />
				</template>
			</AppNavigationBoardCategory>
			<AppNavigationBoardCategory id="deck-navigation-archived"
				to="/board/archived"
				:text="t('deck', 'Archived boards')"
				:boards="archivedBoards">
				<template #icon>
					<ArchiveIcon :size="20" decorative />
				</template>
			</AppNavigationBoardCategory>
			<AppNavigationBoardCategory id="deck-navigation-shared"
				to="/board/shared"
				:text="t('deck', 'Shared with you')"
				:boards="sharedBoards"
				icon="icon-shared">
				<template #icon>
					<ShareVariantIcon :size="20" decorative />
				</template>
			</AppNavigationBoardCategory>
			<AppNavigationAddBoard v-if="canCreate" />
		</template>
		<template #footer>
			<NcAppNavigationSettings :title="t('deck', 'Deck settings')">
				<div>
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

					<NcMultiselect v-if="isAdmin"
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
			</NcAppNavigationSettings>
		</template>
	</NcAppNavigation>
</template>

<script>
import axios from '@nextcloud/axios'
import { mapGetters } from 'vuex'
import ClickOutside from 'vue-click-outside'
import { NcAppNavigation, NcAppNavigationItem, NcAppNavigationSettings, NcMultiselect } from '@nextcloud/vue'
import AppNavigationAddBoard from './AppNavigationAddBoard'
import AppNavigationBoardCategory from './AppNavigationBoardCategory'
import { loadState } from '@nextcloud/initial-state'
import { generateOcsUrl } from '@nextcloud/router'
import { getCurrentUser } from '@nextcloud/auth'
import ArchiveIcon from 'vue-material-design-icons/Archive.vue'
import CalendarIcon from 'vue-material-design-icons/Calendar.vue'
import DeckIcon from './../icons/DeckIcon.vue'
import ShareVariantIcon from 'vue-material-design-icons/Share.vue'

const canCreateState = loadState('deck', 'canCreate')

export default {
	name: 'AppNavigation',
	components: {
		NcAppNavigation,
		NcAppNavigationSettings,
		AppNavigationAddBoard,
		AppNavigationBoardCategory,
		NcMultiselect,
		NcAppNavigationItem,
		ArchiveIcon,
		CalendarIcon,
		DeckIcon,
		ShareVariantIcon,
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
</style>
