<!--
  - SPDX-FileCopyrightText: 2018 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<NcAppSidebar v-if="boardStore.currentBoard && currentCard"
		ref="cardSidebar"
		:active="tabId"
		:name="displayTitle"
		:subtitle="subtitleTooltip"
		:name-editable.sync="isEditingTitle"
		@update:name="(value) => titleEditing = value"
		@update:active="(value) => activeTabId = value"
		@dismiss-editing="titleEditing = currentCard.title"
		@submit-name="handleSubmitTitle"
		@opened="focusHeader"
		@close="closeSidebar">
		<template #subname>
			<span>{{ subtitle }}</span>
			<template v-if="cardOwner">
				<span> ⸱ </span>
				<NcUserBubble :user="cardOwner.uid" :display-name="cardOwner.displayName" />
			</template>
		</template>
		<template #secondary-actions>
			<NcActionButton v-if="cardDetailsInModal && settingsStore.isFullApp" icon="icon-menu-sidebar" @click.stop="closeModal()">
				{{ t('deck', 'Open in sidebar view') }}
			</NcActionButton>
			<NcActionButton v-else-if="settingsStore.isFullApp" icon="icon-external" @click.stop="showModal()">
				{{ t('deck', 'Open in bigger view') }}
			</NcActionButton>

			<CardMenuEntries :card="currentCard" :hide-details-entry="true" />
		</template>
		<template #description>
			<NcReferenceList v-if="currentCard.referenceData"
				:text="currentCard.title"
				:interactive="false" />
		</template>

		<NcAppSidebarTab id="details"
			:order="0"
			:name="t('deck', 'Details')">
			<CardSidebarTabDetails :card="currentCard" />
			<template #icon>
				<HomeIcon v-if="activeTabId === 'details'" :size="20" />
				<HomeOutlineIcon v-else :size="20" />
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
				<CommentIcon v-if="activeTabId === 'comments'" :size="20" />
				<CommentOutlineIcon v-else :size="20" />
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

<script setup>
import { NcActionButton, NcAppSidebar, NcAppSidebarTab, NcUserBubble, useFormatRelativeTime, useFormatTime } from '@nextcloud/vue'
import { NcReferenceList } from '@nextcloud/vue/components/NcRichText'
import { getCapabilities } from '@nextcloud/capabilities'
import CardSidebarTabDetails from './CardSidebarTabDetails.vue'
import CardSidebarTabAttachments from './CardSidebarTabAttachments.vue'
import CardSidebarTabComments from './CardSidebarTabComments.vue'
import CardSidebarTabActivity from './CardSidebarTabActivity.vue'
import relativeDate from '../../mixins/relativeDate.js'
import moment from '@nextcloud/moment'
import AttachmentIcon from 'vue-material-design-icons/Paperclip.vue'
import HomeIcon from 'vue-material-design-icons/Home.vue'
import HomeOutlineIcon from 'vue-material-design-icons/HomeOutline.vue'
import CommentIcon from 'vue-material-design-icons/Comment.vue'
import CommentOutlineIcon from 'vue-material-design-icons/CommentOutline.vue'
import ActivityIcon from 'vue-material-design-icons/LightningBolt.vue'

import { showError, showWarning } from '@nextcloud/dialogs'
import { getLocale } from '@nextcloud/l10n'
import CardMenuEntries from '../cards/CardMenuEntries.vue'
import { mapActions, mapState } from 'pinia'
import { useCardStore } from '../../stores/card.js'
import { useBoardStore } from '../../stores/board.js'
import { useSettingsStore } from '../../stores/settings.js'
import { computed, nextTick, ref, useTemplateRef, watch } from 'vue'
import { useRouter } from 'vue-router'

const capabilities = getCapabilities()

const router = useRouter()

const { id, tabId, tabQuery } = defineProps({
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
})

const emit = defineEmits(['close'])

const settingsStore = useSettingsStore()
const cardStore = useCardStore()
const boardStore = useBoardStore()

const isEditingTitle = ref(false)
const titleEditing = ref('')
const hasActivity = ref(capabilities && capabilities.activity)
const locale = ref(getLocale())
const activeTabId = ref(tabId || 'details')

const cardSidebar = useTemplateRef('cardSidebar')

const currentCard = computed(() => cardStore.cardById(id))

const cardOwnerDisplayName = computed(() => {
	return currentCard.value.owner?.displayname ?? currentCard.value.owner?.uid ?? currentCard.value.owner ?? null
})
const cardOwner = computed(() => {
	const owner = currentCard.value.owner
	if (!owner) return null
	return {
		uid: owner?.uid ?? (typeof owner === 'string' ? owner : null),
		displayName: cardOwnerDisplayName.value,
	}
})
const modifiedTimestamp = computed(() => currentCard.value?.lastModified ? currentCard.value.lastModified * 1000 : null)
const createdTimestamp = computed(() => currentCard.value?.createdAt ? currentCard.value.createdAt * 1000 : null)

const modifiedDate = useFormatRelativeTime(modifiedTimestamp)
const createdDate = useFormatRelativeTime(createdTimestamp)
const modifiedDateAbsolute = useFormatTime(modifiedTimestamp)
const createdDateAbsolute = useFormatTime(createdTimestamp)

const subtitle = computed(() => t('deck', 'Modified: {modifiedDate} ⸱ Created: {createdDate}', {
	modifiedDate: modifiedDate.value,
	createdDate: createdDate.value,
}))

const subtitleTooltip = computed(() => {
	const owner = cardOwnerDisplayName.value
	if (owner) {
		return t('deck', 'Modified: {modifiedDate}\nCreated: {createdDate}\nCreated by: {owner}', {
			modifiedDate: modifiedDateAbsolute.value,
			createdDate: createdDateAbsolute.value,
			owner,
		})
	}

	return t('deck', 'Modified: {modifiedDate}\nCreated: {createdDate}', {
		modifiedDate: modifiedDate.value,
		createdDate: createdDate.value,
	})
})

const cardDetailsInModal = computed({
	get() {
		return settingsStore.configByKey('cardDetailsInModal')
	},
	set(newValue) {
		settingsStore.setConfig({ cardDetailsInModal: newValue })
	},
})

const displayTitle = computed(() => {
	if (isEditingTitle.value) {
		return titleEditing.value
	}
	const reference = currentCard.value.referenceData
	return reference ? reference.openGraphObject.name : currentCard.value.title
})

watch(() => currentCard.value?.title, (newTitle) => {
	titleEditing.value = newTitle
})

watch(() => currentCard.value, (newCard, oldCard) => {
	if (newCard?.id === oldCard?.id) return
	focusHeader()
})

function focusHeader() {
	nextTick(() => {
		const header = cardSidebar.value?.$el.querySelector('.app-sidebar-header__mainname')
		if (header) {
			header.focus()
		}
	})
}

function handleSubmitTitle() {
	if (titleEditing.value.trim() === '') {
		showError(t('deck', 'The title cannot be empty.'))
		return
	}
	isEditingTitle.value = false
	cardStore.updateCardTitleInStore({
		...currentCard.value,
		title: titleEditing.value,
	})
}

function closeSidebar() {
	if (cardStore.hasCardSaveError) {
		showWarning(t('deck', 'Cannot close unsaved card!'))
		return
	}
	router.push({ name: 'board' })
	emit('close')
}

function showModal() {
	settingsStore.setConfig({ cardDetailsInModal: true })
}
function closeModal() {
	settingsStore.setConfig({ cardDetailsInModal: false })
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
	box-sizing: unset;
	$modal-padding: 14px;
	border: 0;
	min-width: calc(100% - #{$modal-padding * 2});
	position: relative;
	top: 0;
	inset-inline: 0;
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
