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
			<NcAppNavigationItem :name="t('deck', 'Upcoming cards')"
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
				:default-open="true"
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
			<NcAppNavigationSettings :name="t('deck', 'Deck settings')">
				<NcButton @click="showHelp = true">
					{{ t('deck', 'Keyboard shortcuts') }}
				</NcButton>
				<HelpModal v-if="showHelp" @close="showHelp=false" />
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
						<input id="toggle-idbadge"
							v-model="cardIdBadge"
							type="checkbox"
							class="checkbox">
						<label for="toggle-idbadge">
							{{ t('deck', 'Show card ID badge') }}
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

					<NcSelect v-if="isAdmin"
						v-model="groupLimit"
						:class="{'icon-loading-small': groupLimitDisabled}"
						open-direction="bottom"
						:options="groups"
						:multiple="true"
						:disabled="groupLimitDisabled"
						:input-label="t('deck', 'Limit board creation to some groups')"
						label="displayname"
						track-by="id"
						@input="updateConfig" />
					<p v-if="isAdmin">
						{{ t('deck', 'Users outside of those groups will not be able to create their own boards, but will still be able to work on boards that have been shared with them.') }}
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
import { NcAppNavigation, NcAppNavigationItem, NcAppNavigationSettings, NcSelect, NcButton } from '@nextcloud/vue'
import AppNavigationAddBoard from './AppNavigationAddBoard.vue'
import AppNavigationBoardCategory from './AppNavigationBoardCategory.vue'
import { loadState } from '@nextcloud/initial-state'
import { generateOcsUrl } from '@nextcloud/router'
import { getCurrentUser } from '@nextcloud/auth'
import ArchiveIcon from 'vue-material-design-icons/Archive.vue'
import CalendarIcon from 'vue-material-design-icons/Calendar.vue'
import DeckIcon from './../icons/DeckIcon.vue'
import ShareVariantIcon from 'vue-material-design-icons/Share.vue'
import HelpModal from './../modals/HelpModal.vue'
import { subscribe } from '@nextcloud/event-bus'

const canCreateState = loadState('deck', 'canCreate')

export default {
	name: 'AppNavigation',
	components: {
		NcAppNavigation,
		NcAppNavigationSettings,
		NcButton,
		AppNavigationAddBoard,
		AppNavigationBoardCategory,
		NcSelect,
		NcAppNavigationItem,
		ArchiveIcon,
		CalendarIcon,
		DeckIcon,
		ShareVariantIcon,
		HelpModal,
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
			showHelp: false,
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
		cardIdBadge: {
			get() {
				return this.$store.getters.config('cardIdBadge')
			},
			set(newValue) {
				this.$store.dispatch('setConfig', { cardIdBadge: newValue })
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
	mounted() {
		subscribe('deck:global:toggle-help-dialog', () => {
			this.showHelp = !this.showHelp
		})
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
