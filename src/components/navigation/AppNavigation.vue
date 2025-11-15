<!--
  - SPDX-FileCopyrightText: 2018 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<NcAppNavigation :class="{'icon-loading': loading}">
		<template #list>
			<NcAppNavigationItem :name="t('deck', 'Upcoming cards')"
				:exact="true"
				to="/">
				<template #icon>
					<CalendarIcon v-if="$route.path === '/'" :size="20" />
					<CalendarOutlineIcon v-else :size="20" />
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
					<ArchiveIcon v-if="$route.path === '/board/archived'" :size="20" decorative />
					<ArchiveOutlineIcon v-else :size="20" decorative />
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
			<AppNavigationImportBoard v-if="canCreate" />
		</template>
		<template #default>
			<DeckAppSettings :open.sync="settingsOpened"
				@close="onSettingsClose" />
		</template>
		<template #footer>
			<ul class="app-navigation-entry__settings">
				<NcAppNavigationItem :name="t('deck', 'Deck settings')"
					@click.prevent.stop="openSettings">
					<template #icon>
						<IconCog :size="20" />
					</template>
				</NcAppNavigationItem>
			</ul>
		</template>
	</NcAppNavigation>
</template>

<script>
import { mapGetters } from 'vuex'
import ClickOutside from 'vue-click-outside'
import { NcAppNavigation, NcAppNavigationItem } from '@nextcloud/vue'
import AppNavigationAddBoard from './AppNavigationAddBoard.vue'
import AppNavigationBoardCategory from './AppNavigationBoardCategory.vue'
import { loadState } from '@nextcloud/initial-state'
import ArchiveIcon from 'vue-material-design-icons/Archive.vue'
import ArchiveOutlineIcon from 'vue-material-design-icons/ArchiveOutline.vue'
import CalendarIcon from 'vue-material-design-icons/Calendar.vue'
import CalendarOutlineIcon from 'vue-material-design-icons/CalendarOutline.vue'
import DeckIcon from './../icons/DeckIcon.vue'
import ShareVariantIcon from 'vue-material-design-icons/ShareOutline.vue'
import { subscribe } from '@nextcloud/event-bus'
import AppNavigationImportBoard from './AppNavigationImportBoard.vue'
import DeckAppSettings from '../DeckAppSettings.vue'
import IconCog from 'vue-material-design-icons/CogOutline.vue'

const canCreateState = loadState('deck', 'canCreate')

export default {
	name: 'AppNavigation',
	components: {
		NcAppNavigation,
		AppNavigationAddBoard,
		AppNavigationBoardCategory,
		AppNavigationImportBoard,
		NcAppNavigationItem,
		ArchiveIcon,
		ArchiveOutlineIcon,
		CalendarIcon,
		CalendarOutlineIcon,
		DeckIcon,
		ShareVariantIcon,
		DeckAppSettings,
		IconCog,
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
			settingsOpened: false,
		}
	},
	computed: {
		...mapGetters([
			'noneArchivedBoards',
			'archivedBoards',
			'sharedBoards',
		]),
	},
	mounted() {
		subscribe('deck:global:toggle-help-dialog', () => {
			this.showHelp = !this.showHelp
		})
	},
	methods: {
		openSettings() {
			this.settingsOpened = true
		},
		onSettingsClose() {
			this.settingsOpened = false
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

	.app-navigation-entry__settings {
		height: auto !important;
		overflow: hidden !important;
		padding-top: 0 !important;
		// Prevent shrinking or growing
		flex: 0 0 auto;
	}
</style>
