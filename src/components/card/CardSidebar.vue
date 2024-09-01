<!--
  - SPDX-FileCopyrightText: 2018 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<NcAppSidebar v-if="currentBoard && currentCard"
		ref="cardSidebar"
		:active="tabId"
		:name="displayTitle"
		:subname="subtitle"
		:subtitle="subtitleTooltip"
		:name-editable="titleEditable"
		@update:nameEditable="handleUpdateTitleEditable"
		@update:name="handleUpdateTitle"
		@submit-name="handleSubmitTitle"
		@opened="focusHeader"
		@close="closeSidebar">
		<template #secondary-actions>
			<NcActionButton v-if="cardDetailsInModal && isFullApp" icon="icon-menu-sidebar" @click.stop="closeModal()">
				{{ t('deck', 'Open in sidebar view') }}
			</NcActionButton>
			<NcActionButton v-else-if="isFullApp" icon="icon-external" @click.stop="showModal()">
				{{ t('deck', 'Open in bigger view') }}
			</NcActionButton>

			<CardMenuEntries :card="currentCard" :hide-details-entry="true" />
		</template>
		<template #description>
			<NcReferenceList v-if="currentCard.referenceData"
				:text="currentCard.title"
				:reference-data="[currentCard.referenceData]"
				:interactive="false" />
		</template>

		<NcAppSidebarTab id="details"
			:order="0"
			:name="t('deck', 'Details')">
			<CardSidebarTabDetails :card="currentCard" />
			<template #icon>
				<HomeIcon :size="20" />
			</template>
		</NcAppSidebarTab>

		<NcAppSidebarTab id="attachments"
			:order="1"
			:name="t('deck', 'Attachments')">
			<template #icon>
				<AttachmentIcon :size="20" />
			</template>
			<CardSidebarTabAttachments :card="currentCard" />
		</NcAppSidebarTab>

		<NcAppSidebarTab id="comments"
			:order="2"
			:name="t('deck', 'Comments')">
			<template #icon>
				<CommentIcon :size="20" />
			</template>
			<CardSidebarTabComments :card="currentCard" :tab-query="tabQuery" />
		</NcAppSidebarTab>

		<NcAppSidebarTab v-if="hasActivity"
			id="timeline"
			:order="3"
			:name="t('deck', 'Activity')">
			<template #icon>
				<ActivityIcon :size="20" />
			</template>
			<CardSidebarTabActivity :card="currentCard" />
		</NcAppSidebarTab>
	</NcAppSidebar>
</template>

<script>
import { NcActionButton, NcAppSidebar, NcAppSidebarTab } from '@nextcloud/vue'
import { NcReferenceList } from '@nextcloud/vue/dist/Components/NcRichText.js'
import { getCapabilities } from '@nextcloud/capabilities'
import { mapState, mapGetters } from 'vuex'
import CardSidebarTabDetails from './CardSidebarTabDetails.vue'
import CardSidebarTabAttachments from './CardSidebarTabAttachments.vue'
import CardSidebarTabComments from './CardSidebarTabComments.vue'
import CardSidebarTabActivity from './CardSidebarTabActivity.vue'
import relativeDate from '../../mixins/relativeDate.js'
import moment from '@nextcloud/moment'
import AttachmentIcon from 'vue-material-design-icons/Paperclip.vue'
import HomeIcon from 'vue-material-design-icons/Home.vue'
import CommentIcon from 'vue-material-design-icons/Comment.vue'
import ActivityIcon from 'vue-material-design-icons/LightningBolt.vue'

import { showError } from '@nextcloud/dialogs'
import { getLocale } from '@nextcloud/l10n'
import CardMenuEntries from '../cards/CardMenuEntries.vue'

const capabilities = getCapabilities()

export default {
	name: 'CardSidebar',
	components: {
		NcAppSidebar,
		NcAppSidebarTab,
		NcActionButton,
		NcReferenceList,
		CardSidebarTabAttachments,
		CardSidebarTabComments,
		CardSidebarTabActivity,
		CardSidebarTabDetails,
		ActivityIcon,
		AttachmentIcon,
		CommentIcon,
		HomeIcon,
		CardMenuEntries,
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
			isFullApp: state => state.isFullApp,
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
			return t('deck', 'Modified') + ': ' + this.relativeDate(this.currentCard.lastModified * 1000) + ' ⸱ ' + t('deck', 'Created') + ': ' + this.relativeDate(this.currentCard.createdAt * 1000)
		},
		subtitleTooltip() {
			return t('deck', 'Modified') + ': ' + this.formatDate(this.currentCard.lastModified) + '\n' + t('deck', 'Created') + ': ' + this.formatDate(this.currentCard.createdAt)
		},
		cardDetailsInModal: {
			get() {
				return this.$store.getters.config('cardDetailsInModal')
			},
			set(newValue) {
				this.$store.dispatch('setConfig', { cardDetailsInModal: newValue })
			},
		},
		displayTitle() {
			const reference = this.currentCard.referenceData
			return reference ? reference.richObject.name : this.currentCard.title
		},
	},
	watch: {
		currentCard() {
			this.focusHeader()
		},
	},
	methods: {
		focusHeader() {
			this.$nextTick(() => {
				this.$refs?.cardSidebar.$el.querySelector('.app-sidebar-header__mainname')?.focus()
			})
		},
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
			this.$router?.push({ name: 'board' })
			this.$emit('close')
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

<style lang="scss">

section.app-sidebar__tab--active {
	min-height: auto;
	display: flex;
	flex-direction: column;
	height: 100%;
}

.modal-container {
	overflow: hidden;
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
	min-height: calc(100vh - var(--header-height) * 4);
	padding: 0 14px;
	height: 97%;
	overflow: hidden !important;
	user-select: text;
	-webkit-user-select: text;

	.app-sidebar-header__mainname-container {
		padding-top: calc(var(--default-grid-baseline, 4px) * 2);
	}

	.app-sidebar-tabs {
		max-height: 90%;
	}

	.app-sidebar__tab {
		min-height: calc(100% - 20px);
		max-height: calc(100% - 20px);
	}

	// FIXME: test
	&:deep {
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
