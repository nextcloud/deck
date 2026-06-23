<!--
  - SPDX-FileCopyrightText: 2018 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<NcAppSidebar v-if="currentBoard && currentCard"
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

<script>
import { NcActionButton, NcAppSidebar, NcAppSidebarTab, NcUserBubble } from '@nextcloud/vue'
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
import HomeOutlineIcon from 'vue-material-design-icons/HomeOutline.vue'
import CommentIcon from 'vue-material-design-icons/Comment.vue'
import CommentOutlineIcon from 'vue-material-design-icons/CommentOutline.vue'
import ActivityIcon from 'vue-material-design-icons/LightningBolt.vue'

import { showError, showWarning } from '@nextcloud/dialogs'
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
		CommentOutlineIcon,
		HomeIcon,
		HomeOutlineIcon,
		CardMenuEntries,
		NcUserBubble,
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
			activeTabId: this.tabId || 'details',
		}
	},
	computed: {
		...mapState({
			isFullApp: (state) => state.isFullApp,
			currentBoard: (state) => state.currentBoard,
			hasCardSaveError: (state) => state.hasCardSaveError,
		}),
		...mapGetters(['canEdit', 'assignables', 'stackById']),
		currentCard() {
			return this.$store.getters.cardById(this.id)
		},
		cardOwnerDisplayName() {
			return this.currentCard.owner?.displayname ?? this.currentCard.owner?.uid ?? this.currentCard.owner ?? null
		},
		cardOwner() {
			const owner = this.currentCard.owner
			if (!owner) return null
			return {
				uid: owner?.uid ?? (typeof owner === 'string' ? owner : null),
				displayName: this.cardOwnerDisplayName,
			}
		},
		subtitle() {
			const modifiedDate = this.relativeDate(this.currentCard.lastModified * 1000)
			const createdDate = this.relativeDate(this.currentCard.createdAt * 1000)
			return t('deck', 'Modified: {modifiedDate} ⸱ Created: {createdDate}', { modifiedDate, createdDate })
		},
		subtitleTooltip() {
			const modifiedDate = this.formatDate(this.currentCard.lastModified)
			const createdDate = this.formatDate(this.currentCard.createdAt)
			const owner = this.cardOwnerDisplayName
			if (owner) {
				return t('deck', 'Modified: {modifiedDate}\nCreated: {createdDate}\nCreated by: {owner}', { modifiedDate, createdDate, owner })
			}
			return t('deck', 'Modified: {modifiedDate}\nCreated: {createdDate}', { modifiedDate, createdDate })
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
	},
	watch: {
		currentCard(newCard, oldCard) {
			if (newCard.id === oldCard.id) return
			this.focusHeader()
		},
		'currentCard.title': {
			immediate: true,
			handler(newTitle) {
				this.titleEditing = newTitle
			},
		},
		isEditingTitle(editing) {
			if (editing) {
				// The sidebar header uses a single-line <input> for the title.
				// Replace it with a <textarea> so long titles wrap and stay readable.
				this.$nextTick(this.setupTitleTextarea)
			}
		},
	},
	methods: {
		setupTitleTextarea() {
			const root = this.$refs?.cardSidebar?.$el
			const input = root?.querySelector('input.app-sidebar-header__mainname-input')
			if (!input || input.dataset.deckTextarea === 'done') {
				return
			}

			const textarea = document.createElement('textarea')
			textarea.className = input.className
			textarea.value = this.titleEditing ?? ''
			textarea.rows = 1
			textarea.placeholder = input.getAttribute('placeholder') || ''
			const ariaLabel = input.getAttribute('aria-label')
			if (ariaLabel) {
				textarea.setAttribute('aria-label', ariaLabel)
			}

			// Keep the original input in the DOM (hidden) so the library's form
			// submit / click-outside handling keeps working, but show our textarea.
			input.style.display = 'none'
			input.dataset.deckTextarea = 'done'
			input.after(textarea)

			const autoGrow = () => {
				textarea.style.height = 'auto'
				textarea.style.height = textarea.scrollHeight + 'px'
			}

			textarea.addEventListener('input', () => {
				this.titleEditing = textarea.value
				autoGrow()
			})
			textarea.addEventListener('keydown', (event) => {
				// Ignore Enter while composing (e.g. IME for RTL/CJK input).
				if (event.isComposing || event.keyCode === 229) {
					return
				}
				if (event.key === 'Enter') {
					// Enter applies the title instead of adding a new line.
					event.preventDefault()
					event.stopPropagation()
					this.handleSubmitTitle()
				} else if (event.key === 'Escape' || event.key === 'Esc') {
					event.preventDefault()
					event.stopPropagation()
					this.titleEditing = this.currentCard.title
					this.isEditingTitle = false
				}
			})

			this.$nextTick(() => {
				textarea.focus()
				const end = textarea.value.length
				textarea.setSelectionRange(end, end)
				autoGrow()
			})
		},
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
	},
}
</script>

<style lang="scss">

// Multi-line title editor injected in place of the sidebar header's single-line
// <input>. Mirrors the look of `input.app-sidebar-header__mainname-input`.
textarea.app-sidebar-header__mainname-input {
	flex: 1 1 auto;
	min-width: 0;
	margin: 0;
	padding: 7px;
	font-size: 20px;
	font-weight: bold;
	line-height: 1.3;
	font-family: inherit;
	resize: none;
	overflow: hidden;
	word-break: break-word;
	overflow-wrap: anywhere;
}

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
		// Allow the title to wrap onto multiple lines instead of being clipped
		align-items: flex-start;
	}

	// Show the full title instead of truncating it with an ellipsis
	.app-sidebar-header__mainname {
		overflow: visible !important;
		white-space: normal !important;
		text-overflow: clip !important;
		word-break: break-word;
		overflow-wrap: anywhere;
	}

	// Let the edit input grow to show the whole title
	.app-sidebar-header__mainname-form {
		min-width: 0;

		.app-sidebar-header__mainname-input {
			min-width: 0;
		}
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
