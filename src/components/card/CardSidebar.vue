<!--
  - @copyright Copyright (c) 2018 Julius Härtl <jus@bitgrid.net>
  -
  - @author Julius Härtl <jus@bitgrid.net>
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
  - MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
  - GNU Affero General Public License for more details.
  -
  - You should have received a copy of the GNU Affero General Public License
  - along with this program. If not, see <http://www.gnu.org/licenses/>.
  -
  -->

<template>
	<NcAppSidebar v-if="currentBoard && currentCard"
		:active="tabId"
		:title="title"
		:subtitle="subtitle"
		:subtitle-tooltip="subtitleTooltip"
		:title-editable="titleEditable"
		@update:titleEditable="handleUpdateTitleEditable"
		@update:title="handleUpdateTitle"
		@submit-title="handleSubmitTitle"
		@close="closeSidebar">
		<template #secondary-actions>
			<NcActionButton v-if="cardDetailsInModal" icon="icon-menu-sidebar" @click.stop="closeModal()">
				{{ t('deck', 'Open in sidebar view') }}
			</NcActionButton>
			<NcActionButton v-else icon="icon-external" @click.stop="showModal()">
				{{ t('deck', 'Open in bigger view') }}
			</NcActionButton>

			<NcActionButton v-for="action in cardActions"
				:key="action.label"
				:close-after-click="true"
				:icon="action.icon"
				@click="action.callback(cardRichObject)">
				{{ action.label }}
			</NcActionButton>
		</template>

		<NcAppSidebarTab id="details"
			:order="0"
			:name="t('deck', 'Details')"
			icon="icon-home">
			<CardSidebarTabDetails :card="currentCard" />
		</NcAppSidebarTab>

		<NcAppSidebarTab id="attachments"
			:order="1"
			:name="t('deck', 'Attachments')">
			<template #icon>
				<AttachmentIcon :size="20" decorative />
			</template>
			<CardSidebarTabAttachments :card="currentCard" />
		</NcAppSidebarTab>

		<NcAppSidebarTab id="comments"
			:order="2"
			:name="t('deck', 'Comments')"
			icon="icon-comment">
			<CardSidebarTabComments :card="currentCard" :tab-query="tabQuery" />
		</NcAppSidebarTab>

		<NcAppSidebarTab v-if="hasActivity"
			id="timeline"
			:order="3"
			:name="t('deck', 'Timeline')"
			icon="icon-activity">
			<CardSidebarTabActivity :card="currentCard" />
		</NcAppSidebarTab>
	</NcAppSidebar>
</template>

<script>
import { NcActionButton, NcAppSidebar, NcAppSidebarTab } from '@nextcloud/vue'
import { generateUrl } from '@nextcloud/router'
import { mapState, mapGetters } from 'vuex'
import CardSidebarTabDetails from './CardSidebarTabDetails'
import CardSidebarTabAttachments from './CardSidebarTabAttachments'
import CardSidebarTabComments from './CardSidebarTabComments'
import CardSidebarTabActivity from './CardSidebarTabActivity'
import relativeDate from '../../mixins/relativeDate'
import moment from '@nextcloud/moment'
import AttachmentIcon from 'vue-material-design-icons/Paperclip.vue'

import { showError } from '@nextcloud/dialogs'
import { getLocale } from '@nextcloud/l10n'

const capabilities = window.OC.getCapabilities()

export default {
	name: 'CardSidebar',
	components: {
		NcAppSidebar,
		NcAppSidebarTab,
		NcActionButton,
		CardSidebarTabAttachments,
		CardSidebarTabComments,
		CardSidebarTabActivity,
		CardSidebarTabDetails,
		AttachmentIcon,
	},
	mixins: [relativeDate],
	props: {
		id: {
			type: Number,
			required: true,
		},
		tabId: {
			type: String,
			required: false,
			default: null,
		},
		tabQuery: {
			type: String,
			required: false,
			default: null,
		},
	},
	data() {
		return {
			titleEditable: false,
			titleEditing: '',
			hasActivity: capabilities && capabilities.activity,
			locale: getLocale(),
		}
	},
	computed: {
		...mapState({
			currentBoard: state => state.currentBoard,
		}),
		...mapGetters(['canEdit', 'assignables', 'cardActions', 'stackById']),
		title() {
			return this.titleEditable ? this.titleEditing : this.currentCard.title
		},
		currentCard() {
			return this.$store.getters.cardById(this.id)
		},
		subtitle() {
			return t('deck', 'Modified') + ': ' + this.relativeDate(this.currentCard.lastModified * 1000) + ' ' + t('deck', 'Created') + ': ' + this.relativeDate(this.currentCard.createdAt * 1000)
		},
		subtitleTooltip() {
			return t('deck', 'Modified') + ': ' + this.formatDate(this.currentCard.lastModified) + '\n' + t('deck', 'Created') + ': ' + this.formatDate(this.currentCard.createdAt)
		},
		cardRichObject() {
			return {
				id: '' + this.currentCard.id,
				name: this.currentCard.title,
				boardname: this.currentBoard.title,
				stackname: this.stackById(this.currentCard.stackId)?.title,
				link: window.location.protocol + '//' + window.location.host + generateUrl('/apps/deck/') + `#/board/${this.currentBoard.id}/card/${this.currentCard.id}`,
			}
		},
		cardDetailsInModal: {
			get() {
				return this.$store.getters.config('cardDetailsInModal')
			},
			set(newValue) {
				this.$store.dispatch('setConfig', { cardDetailsInModal: newValue })
			},
		},
	},
	methods: {
		handleUpdateTitleEditable(value) {
			this.titleEditable = value
			if (value) {
				this.titleEditing = this.currentCard.title
			}
		},
		handleUpdateTitle(value) {
			this.titleEditing = value
		},
		handleSubmitTitle(value) {
			if (value.trim === '') {
				showError(t('deck', 'The title cannot be empty.'))
				return
			}
			this.titleEditable = false
			this.$store.dispatch('updateCardTitle', { ...this.currentCard, title: this.titleEditing })
		},

		closeSidebar() {
			this.$router.push({ name: 'board' })
		},

		showModal() {
			this.$store.dispatch('setConfig', { cardDetailsInModal: true })
		},
		closeModal() {
			this.$store.dispatch('setConfig', { cardDetailsInModal: false })
		},
		formatDate(timestamp) {
			return moment.unix(timestamp).locale(this.locale).format('LLLL')
		},
	},
}
</script>

<style lang="scss" scoped>

	section.app-sidebar__tab--active {
		min-height: auto;
		display: flex;
		flex-direction: column;
		height: 100%;
	}

	// FIXME: Obivously we should at some point not randomly reuse the sidebar component
	// since this is not oficially supported
	.modal__card .app-sidebar {
		$modal-padding: 14px;
		border: 0;
		min-width: calc(100% - #{$modal-padding * 2});
		position: relative;
		top: 0;
		left: 0;
		right: 0;
		max-width: calc(100% - #{$modal-padding * 2});
		padding: 0 14px;
		max-height: 100%;
		overflow: initial;
		user-select: text;
		-webkit-user-select: text;

		&::v-deep {
			.app-sidebar-header {
				position: sticky;
				top: 0;
				z-index: 100;
				background-color: var(--color-main-background);
			}
			.app-sidebar-tabs__nav {
				position: sticky;
				top: 87px;
				margin: 0;
				z-index: 100;
				background-color: var(--color-main-background);
			}

			.app-sidebar__tab {
				overflow: initial;
			}

			#emptycontent, .emptycontent {
				margin-top: 88px;
			}
		}
	}

</style>
