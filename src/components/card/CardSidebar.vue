<!--
  - SPDX-FileCopyrightText: 2018 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<!-- eslint-disable vue/no-v-model-argument -->
<template>
	<NcAppSidebar v-if="currentBoard && currentCard"
		ref="cardSidebar"
		v-model:name-editable="isEditingTitle"
		:active="tabId"
		:name="displayTitle"
		:subname="subtitle"
		:subtitle="subtitleTooltip"
		@update:name="value => titleEditing = value"
		@dismiss-editing="titleEditing = currentCard.title"
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

			<NcActionButton v-if="canEdit && !isCurrentUserAssigned"
				icon="icon-user"
				:close-after-click="true"
				@click="assignCardToMe()">
				{{ t('deck', 'Assign to me') }}
			</NcActionButton>
			<NcActionButton v-if="canEdit && isCurrentUserAssigned"
				icon="icon-user"
				:close-after-click="true"
				@click="unassignCardFromMe()">
				{{ t('deck', 'Unassign myself') }}
			</NcActionButton>
			<NcActionButton v-if="canEdit"
				icon="icon-checkmark"
				:close-after-click="true"
				@click="changeCardDoneStatus()">
				{{ currentCard.done ? t('deck', 'Mark as not done') : t('deck', 'Mark as done') }}
			</NcActionButton>
			<NcActionButton v-if="canEdit"
				icon="icon-external"
				:close-after-click="true"
				@click="openCardMoveDialog">
				{{ t('deck', 'Move/copy card') }}
			</NcActionButton>
			<NcActionButton v-for="action in cardActions"
				:key="action.label"
				:close-after-click="true"
				:icon="action.icon"
				@click="action.callback(cardRichObject)">
				{{ action.label }}
			</NcActionButton>
			<NcActionButton v-if="canEditBoard" :close-after-click="true" @click="archiveUnarchiveCard()">
				<template #icon>
					<ArchiveIcon :size="20" decorative />
				</template>
				{{ currentCard.archived ? t('deck', 'Unarchive card') : t('deck', 'Archive card') }}
			</NcActionButton>
			<NcActionButton v-if="canEdit"
				icon="icon-delete"
				:close-after-click="true"
				@click="deleteCard()">
				{{ t('deck', 'Delete card') }}
			</NcActionButton>
		</template>
		<template #description>
			<NcReferenceList v-if="currentCard.referenceData" :text="currentCard.title" :interactive="false" />
		</template>

		<NcAppSidebarTab id="details" :order="0" :name="t('deck', 'Details')">
			<CardSidebarTabDetails :card="currentCard" />
			<template #icon>
				<HomeIcon :size="20" />
			</template>
		</NcAppSidebarTab>

		<NcAppSidebarTab id="attachments" :order="1" :name="t('deck', 'Attachments')">
			<template #icon>
				<AttachmentIcon :size="20" />
			</template>
			<CardSidebarTabAttachments :card="currentCard" />
		</NcAppSidebarTab>

		<NcAppSidebarTab id="comments" :order="2" :name="t('deck', 'Comments')">
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
import { NcActionButton, NcAppSidebar, NcAppSidebarTab, NcReferenceList } from '@nextcloud/vue'
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

import { showError, showWarning, showUndo } from '@nextcloud/dialogs'
import { getLocale } from '@nextcloud/l10n'
import { emit } from '@nextcloud/event-bus'

import ArchiveIcon from 'vue-material-design-icons/Archive.vue'
import { getCurrentUser } from '@nextcloud/auth'
import { generateUrl } from '@nextcloud/router'

import '@nextcloud/dialogs/style.css'

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
		ArchiveIcon,
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
			isEditingTitle: false,
			titleEditing: '',
			hasActivity: capabilities && capabilities.activity,
			locale: getLocale(),
		}
	},
	computed: {
		...mapState({
			isFullApp: (state) => state.isFullApp,
			currentBoard: (state) => state.currentBoard,
			hasCardSaveError: (state) => state.hasCardSaveError,
			showArchived: (state) => state.showArchived,
		}),
		...mapGetters([
			'canEdit',
			'assignables',
			'cardActions',
			'stackById',
			'isArchived',
			'boards',
			'boardById',
		]),
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
		displayTitle: {
			get() {
				if (this.isEditingTitle) {
					return this.titleEditing
				}
				const reference = this.currentCard.referenceData
				return reference ? reference.openGraphObject.name : this.currentCard.title
			},
		},
		canEdit() {
			return !this.currentCard.archived
		},
		canEditBoard() {
			if (this.currentBoard) {
				return this.$store.getters.canEdit
			}
			const board = this.$store.getters.boards.find((item) => item.id === this.currentCard.boardId)
			return !!board?.permissions?.PERMISSION_EDIT
		},
		isCurrentUserAssigned() {
			return this.currentCard.assignedUsers.find((item) => item.type === 0 && item.participant.uid === getCurrentUser()?.uid)
		},
		boardId() {
			return this.card?.boardId ? this.currentCard.boardId : Number(this.$route.params.id)
		},
		cardRichObject() {
			return {
				id: '' + this.currentCard.id,
				name: this.currentCard.title,
				boardname: this.boardById(this.boardId)?.title,
				stackname: this.stackById(this.currentCard.stackId)?.title,
				link: window.location.protocol + '//' + window.location.host + generateUrl('/apps/deck/') + `card/${this.currentCard.id}`,
			}
		},
	},
	watch: {
		currentCard() {
			this.focusHeader()
		},
		'currentCard.title': {
			immediate: true,
			handler(newTitle) {
				this.titleEditing = newTitle
			},
		},
	},
	methods: {
		focusHeader() {
			this.$nextTick(() => {
				this.$refs?.cardSidebar.$el.querySelector('.app-sidebar-header__mainname')?.focus()
			})
		},
		handleSubmitTitle() {
			if (this.titleEditing.trim() === '') {
				showError(t('deck', 'The title cannot be empty.'))
				return
			}
			this.isEditingTitle = false
			this.$store.dispatch('updateCardTitle', {
				...this.currentCard,
				title: this.titleEditing,
			})
		},

		closeSidebar() {
			if (this.hasCardSaveError) {
				showWarning(t('deck', 'Cannot close unsaved card!'))
				return
			}
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
		deleteCard() {
			this.$store.dispatch('deleteCard', this.currentCard)
			const undoCard = { ...this.currentCard, deletedAt: 0 }
			showUndo(t('deck', 'Card deleted'), () => this.$store.dispatch('cardUndoDelete', undoCard))
			if (this.$router.currentRoute.name === 'card') {
				this.$router.push({ name: 'board' })
			}
		},
		changeCardDoneStatus() {
			this.$store.dispatch('changeCardDoneStatus', { ...this.currentCard, done: !this.currentCard.done })
		},
		archiveUnarchiveCard() {
			this.$store.dispatch('archiveUnarchiveCard', { ...this.currentCard, archived: !this.currentCard.archived })
		},
		assignCardToMe() {
			this.$store.dispatch('assignCardToUser', {
				card: this.currentCard,
				assignee: {
					userId: getCurrentUser()?.uid,
					type: 0,
				},
			})
		},
		unassignCardFromMe() {
			this.$store.dispatch('removeUserFromCard', {
				card: this.currentCard,
				assignee: {
					userId: getCurrentUser()?.uid,
					type: 0,
				},
			})
		},
		openCardMoveDialog() {
			emit('deck:card:show-move-dialog', this.currentCard)
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
	box-sizing: unset;
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

		#emptycontent,
		.emptycontent {
			margin-top: 88px;
		}
	}
}
</style>
