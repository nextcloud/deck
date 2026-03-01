<!--
  - SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<NcAppSettingsDialog :open="open"
		:name="t('deck', 'Deck settings')"
		:legacy="false"
		show-navigation
		@update:open="onClose">
		<NcAppSettingsSection id="general-settings" :name="t('deck', 'General')">
			<NcFormBox>
				<NcFormBoxSwitch v-model="cardDetailsInModal"
					:label="t('deck', 'Use bigger card view')" />
			</NcFormBox>
		</NcAppSettingsSection>

		<NcAppSettingsSection id="appearance-settings" :name="t('deck', 'Appearance')">
			<NcFormBox>
				<NcFormBoxSwitch v-model="cardIdBadge"
					:label="t('deck', 'Show card ID badge')" />
				<NcFormBoxSwitch v-model="configCalendar"
					:label="t('deck', 'Show boards in calendar/tasks')" />
			</NcFormBox>
			<NcSelect v-model="caldavListModeSelection"
				:options="caldavListModeOptions"
				:clearable="false"
				label="label"
				track-by="id"
				:input-label="t('deck', 'CalDAV list mapping mode')" />
		</NcAppSettingsSection>

		<NcAppSettingsSection v-if="isAdmin" id="admin-settings" :name="t('deck', 'Admin settings')">
			<NcSelect v-model="groupLimit"
				open-direction="bottom"
				:options="groups"
				:multiple="true"
				:input-label="t('deck', 'Limit board creation to some groups')"
				label="displayname"
				track-by="id"
				@input="updateConfig" />
			<p>
				{{ t('deck', 'Users outside of those groups will not be able to create their own boards, but will still be able to work on boards that have been shared with them.') }}
			</p>
			<NcFormBox>
				<NcFormBoxSwitch v-model="federationEnabled"
					:label="t('deck', 'Enable federation')" />
			</NcFormBox>
		</NcAppSettingsSection>

		<NcAppSettingsShortcutsSection>
			<NcHotkeyList :label="t('deck', 'Board actions')">
				<NcHotkey :label="t('deck', 'Scroll sideways')" hotkey="Shift Scroll" />
				<NcHotkey :label="t('deck', 'Navigate between cards')" hotkey="ArrowUp ArrowDown ArrowLeft ArrowRight" />
				<NcHotkey :label="t('deck', 'Close card details')" hotkey="Escape" />
				<NcHotkey :label="t('deck', 'Search')" hotkey="Control F" />
				<NcHotkey :label="t('deck', 'Show card filters')" hotkey="F" />
				<NcHotkey :label="t('deck', 'Clear card filters')" hotkey="X" />
				<NcHotkey :label="t('deck', 'Show those shortcuts')" hotkey="?" />
			</NcHotkeyList>
			<NcHotkeyList :label="t('deck', 'Card actions')">
				<NcHotkey :label="t('deck', 'Open card details')" hotkey="Enter Space" />
				<NcHotkey :label="t('deck', 'Edit the card title')" hotkey="E" />
				<NcHotkey :label="t('deck', 'Assign yourself to the current card')" hotkey="S" />
				<NcHotkey :label="t('deck', 'Archive/unarchive the current card')" hotkey="A" />
				<NcHotkey :label="t('deck', 'Mark card as completed/not completed')" hotkey="O" />
				<NcHotkey :label="t('deck', 'Open card menu')" hotkey="M" />
			</NcHotkeyList>
		</NcAppSettingsShortcutsSection>
	</NcAppSettingsDialog>
</template>

<script>
import { getCurrentUser } from '@nextcloud/auth'
import { useHotKey } from '@nextcloud/vue/composables/useHotKey'
import { translate as t } from '@nextcloud/l10n'
import NcAppSettingsDialog from '@nextcloud/vue/components/NcAppSettingsDialog'
import NcAppSettingsSection from '@nextcloud/vue/components/NcAppSettingsSection'
import NcAppSettingsShortcutsSection from '@nextcloud/vue/components/NcAppSettingsShortcutsSection'
import NcFormBox from '@nextcloud/vue/components/NcFormBox'
import NcFormBoxSwitch from '@nextcloud/vue/components/NcFormBoxSwitch'
import NcHotkeyList from '@nextcloud/vue/components/NcHotkeyList'
import NcHotkey from '@nextcloud/vue/components/NcHotkey'
import { NcSelect } from '@nextcloud/vue'
import { confirmPassword } from '@nextcloud/password-confirmation'
import '@nextcloud/password-confirmation/style.css' // Required for dialog styles
import axios from '@nextcloud/axios'
import { generateOcsUrl } from '@nextcloud/router'

export default {
	name: 'DeckAppSettings',
	components: {
		NcSelect,
		NcAppSettingsDialog,
		NcAppSettingsSection,
		NcAppSettingsShortcutsSection,
		NcFormBox,
		NcFormBoxSwitch,
		NcHotkeyList,
		NcHotkey,
	},

	props: {
		open: {
			type: Boolean,
			default: false,
		},
	},

	data() {
		return {
			groups: [],
			groupLimit: [],
		}
	},

	computed: {
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
		federationEnabled: {
			get() {
				const value = this.$store.getters.config('federationEnabled')
				return value
			},
			set(newValue) {
				confirmPassword().then(() => {
					this.$store.dispatch('setConfig', { federationEnabled: newValue ? 'yes' : 'no' })
				})
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
		caldavListModeOptions() {
			return [
				{ id: 'root_tasks', label: t('deck', 'Default: lists as root tasks') },
				{ id: 'per_list_calendar', label: t('deck', 'One calendar per list') },
				{ id: 'list_as_category', label: t('deck', 'List name as category on each task') },
				{ id: 'list_as_priority', label: t('deck', 'List position as task priority (1-9)') },
			]
		},
		caldavListModeSelection: {
			get() {
				const current = this.$store.getters.config('caldavListMode') || 'root_tasks'
				return this.caldavListModeOptions.find((option) => option.id === current) || this.caldavListModeOptions[0]
			},
			set(option) {
				if (!option?.id) {
					return
				}
				this.$store.dispatch('setConfig', { caldavListMode: option.id })
			},
		},
	},

	beforeMount() {
		if (this.isAdmin) {
			this.groupLimit = this.$store.getters.config('groupLimit')
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

	created() {
		// ? opens the settings dialog on the keyboard shortcuts section
		useHotKey('?', this.showKeyboardShortcuts, {
			stop: true,
			prevent: true,
		})
	},

	methods: {
		onClose() {
			this.$emit('close')
		},

		async updateConfig() {
			await this.$store.dispatch('setConfig', { groupLimit: this.groupLimit })
		},

		async showKeyboardShortcuts() {
			this.$emit('update:open', true)

			await this.$nextTick()
			document.getElementById('settings-section_keyboard-shortcuts').scrollIntoView({
				behavior: 'smooth',
				inline: 'nearest',
			})
		},
	},
}
</script>

<style lang="scss" scoped>
.app-settings-section {
	&#settings-section_admin-settings p {
		margin-bottom: 20px;
	}
}
</style>
