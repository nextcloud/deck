<!--
	- SPDX-FileCopyrightText: 2018 Nextcloud GmbH and Nextcloud contributors
	- SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<div class="stack" :data-cy-stack="stack.title">
		<div v-click-outside="stopCardCreation"
			class="stack__header"
			:class="{'stack__header--add': showAddCard}"
			:aria-label="stack.title">
			<transition name="fade" mode="out-in">
				<h3 v-if="!canManage || isArchived" tabindex="0">
					{{ stack.title }}
				</h3>
				<h3 v-else-if="!editing"
					title="stack.title"
					dir="auto"
					tabindex="0"
					:aria-label="stack.title"
					class="stack__title"
					@click="startEditing(stack)"
					@keydown.enter="startEditing(stack)">
					{{ stack.title }}
				</h3>
				<form v-else-if="editing"
					v-click-outside="cancelEdit"
					data-cy="editStackTitleForm"
					@submit.prevent="finishedEdit(stack)"
					@keyup.esc="cancelEdit">
					<input v-model="copiedStack.title"
						v-focus
						dir="auto"
						type="text"
						required="required">
					<input title="t('deck', 'Edit list title')"
						class="icon-confirm"
						type="submit"
						value="">
				</form>
			</transition>
			<NcActions v-if="canManage && !isArchived" :force-menu="true">
				<NcActionButton v-if="!showArchived" icon="icon-archive" @click="modalArchivAllCardsShow=true">
					<template #icon>
						<ArchiveIcon decorative />
					</template>
					{{ t('deck', 'Archive all cards') }}
				</NcActionButton>
				<NcActionButton v-if="showArchived" @click="modalArchivAllCardsShow=true">
					<template #icon>
						<ArchiveIcon decorative />
					</template>
					{{ t('deck', 'Unarchive all cards') }}
				</NcActionButton>
				<NcActionButton icon="icon-delete" @click="deleteStack(stack)">
					{{ t('deck', 'Delete list') }}
				</NcActionButton>
			</NcActions>
			<NcActions v-if="canEdit && !showArchived && !isArchived">
				<NcActionButton data-cy="action:add-card" @click.stop="showAddCard=true">
					{{ t('deck', 'Add card') }}
					<template #icon>
						<CardPlusOutline :size="20" />
					</template>
				</NcActionButton>
			</NcActions>
		</div>

		<NcModal v-if="modalArchivAllCardsShow" @close="modalArchivAllCardsShow=false">
			<div class="modal__content">
				<h3 v-if="!showArchived">
					{{ t('deck', 'Archive all cards in this list') }}
				</h3>
				<h3 v-else>
					{{ t('deck', 'Unarchive all cards in this list') }}
				</h3>

				<progress :value="stackTransfer.current" :max="stackTransfer.total" />
				<button v-if="!showArchived" class="primary" @click="setArchivedToAllCardsFromStack(stack, !showArchived)">
					{{ t('deck', 'Archive all cards') }}
				</button>
				<button v-else class="primary" @click="setArchivedToAllCardsFromStack(stack, !showArchived)">
					{{ t('deck', 'Unarchive all cards') }}
				</button>
				<button @click="modalArchivAllCardsShow=false">
					{{ t('deck', 'Cancel') }}
				</button>
			</div>
		</NcModal>

		<Container :get-child-payload="payloadForCard(stack.id)"
			group-name="stack"
			data-click-closes-sidebar="true"
			non-drag-area-selector=".dragDisabled"
			:drag-handle-selector="dragHandleSelector"
			data-dragscroll-enabled
			@should-accept-drop="canEdit"
			@drag-start="draggingCard = true"
			@drag-end="draggingCard = false"
			@drop="($event) => onDropCard(stack.id, $event)">
			<Draggable v-for="card in cardsByStack" :key="card.id">
				<transition :appear="animate && !card.animated && (card.animated=true)"
					:appear-class="'zoom-appear-class'"
					:appear-active-class="'zoom-appear-active-class'">
					<CardItem :id="card.id" ref="card" :dragging="draggingCard" />
				</transition>
			</Draggable>
		</Container>

		<transition name="slide-bottom" appear>
			<div v-show="showAddCard" class="stack__card-add">
				<form :class="{ 'icon-loading-small': stateCardCreating }"
					@submit.prevent.stop="clickAddCard()">
					<label for="new-stack-input-main" class="hidden-visually">{{ t('deck', 'Add a new card') }}</label>
					<input id="new-stack-input-main"
						ref="newCardInput"
						v-model="newCardTitle"
						type="text"
						class="no-close"
						:disabled="stateCardCreating"
						:placeholder="t('deck', 'Card name')"
						required
						pattern=".*\S+.*"
						@focus="onCreateCardFocus"
						@keydown.esc="stopCardCreation">
					<input v-show="!stateCardCreating"
						class="icon-confirm"
						type="submit"
						value="">
				</form>
			</div>
		</transition>
	</div>
</template>

<script>
import ClickOutside from 'vue-click-outside'
import { mapGetters, mapState } from 'vuex'
import { Container, Draggable } from 'vue-smooth-dnd'
import ArchiveIcon from 'vue-material-design-icons/Archive.vue'
import CardPlusOutline from 'vue-material-design-icons/CardPlusOutline.vue'
import { NcActions, NcActionButton, NcModal } from '@nextcloud/vue'
import { showError, showUndo } from '@nextcloud/dialogs'

import CardItem from '../cards/CardItem.vue'

import '@nextcloud/dialogs/style.css'

export default {
	name: 'Stack',
	components: {
		NcActions,
		NcActionButton,
		CardItem,
		Container,
		Draggable,
		NcModal,
		ArchiveIcon,
		CardPlusOutline,
	},
	directives: {
		ClickOutside,
	},
	props: {
		dragging: {
			type: Boolean,
			default: false,
		},
		stack: {
			type: Object,
			default: undefined,
		},
	},
	data() {
		return {
			editing: false,
			draggingCard: false,
			copiedStack: '',
			newCardTitle: '',
			showAddCard: false,
			stateCardCreating: false,
			animate: false,
			modalArchivAllCardsShow: false,
			stackTransfer: {
				total: 0,
				current: null,
			},
		}
	},
	computed: {
		...mapGetters([
			'canManage',
			'canEdit',
			'isArchived',
		]),
		...mapState({
			showArchived: state => state.showArchived,
		}),
		cardsByStack() {
			return this.$store.getters.cardsByStack(this.stack.id).filter((card) => {
				if (this.showArchived) {
					return card.archived
				}
				return !card.archived
			})
		},
		dragHandleSelector() {
			return this.canEdit && !this.showArchived ? null : '.no-drag'
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
	watch: {
		showAddCard(newValue) {
			if (!newValue) {
				this.$store.dispatch('toggleShortcutLock', false)
			} else {
				this.$nextTick(() => {
					this.$refs.newCardInput.focus()
				})
			}
		},
	},

	methods: {
		stopCardCreation(e) {
			// For some reason the submit event triggers a MouseEvent that is bubbling to the outside
			// so we have to ignore it
			e.stopPropagation()
			if (this.$refs.newCardInput && this.$refs.newCardInput.parentElement === e.target.parentElement) {
				return false
			}
			this.showAddCard = false
			return false
		},
		async onDropCard(stackId, event) {
			const { addedIndex, removedIndex, payload } = event
			const card = Object.assign({}, payload)
			if (this.stack.id === stackId) {
				if (addedIndex !== null && removedIndex === null) {
					// move card to new stack
					card.stackId = stackId
					card.order = addedIndex
					console.debug('move card to stack', card.stackId, card.order)
					await this.$store.dispatch('reorderCard', card)
				}
				if (addedIndex !== null && removedIndex !== null) {
					card.order = addedIndex
					console.debug('move card in stack', card.stackId, card.order)
					await this.$store.dispatch('reorderCard', card)
				}
			}
		},
		payloadForCard(stackId) {
			return index => {
				return this.cardsByStack[index]
			}
		},
		deleteStack(stack) {
			this.$store.dispatch('deleteStack', stack)
			showUndo(t('deck', 'List deleted'), () => this.$store.dispatch('stackUndoDelete', stack))
		},
		setArchivedToAllCardsFromStack(stack, isArchived) {

			this.stackTransfer.total = this.cardsByStack.length
			this.cardsByStack.forEach((card, index) => {
				this.stackTransfer.current = index
				this.$store.dispatch('archiveUnarchiveCard', { ...card, archived: isArchived })
			})
			this.modalArchivAllCardsShow = false
		},
		startEditing(stack) {
			if (this.dragging) {
				return
			}

			this.copiedStack = Object.assign({}, stack)
			this.editing = true
		},
		finishedEdit(stack) {
			if (this.copiedStack.title !== stack.title) {
				this.$store.dispatch('updateStack', this.copiedStack)
			}
			this.editing = false
		},
		cancelEdit() {
			this.editing = false
		},
		async clickAddCard() {
			this.stateCardCreating = true
			try {
				this.animate = true
				const newCard = await this.$store.dispatch('addCard', {
					title: this.newCardTitle,
					stackId: this.stack.id,
					boardId: this.stack.boardId,
				})
				this.newCardTitle = ''
				this.showAddCard = true
				this.$nextTick(() => {
					this.$refs.newCardInput.focus()
					this.animate = false
					this.$refs.card[(this.$refs.card.length - 1)].scrollIntoView()
				})
				if (!this.cardDetailsInModal) {
					this.$router.push({ name: 'card', params: { cardId: newCard.id } })
				}
			} catch (e) {
				showError('Could not create card: ' + e.response.data.message)
			} finally {
				this.stateCardCreating = false
			}
		},
		onCreateCardFocus() {
			this.$store.dispatch('toggleShortcutLock', true)
		},
	},
}
</script>

<style lang="scss" scoped>

	@use 'sass:math';

	@import './../../css/variables';

	.stack {
		width: $stack-width + $stack-spacing * 3;
	}

	.stack__header {
		display: flex;
		position: sticky;
		top: 0;
		z-index: 100;
		padding-left: $card-spacing;
		padding-right: $card-spacing;
		margin: 6px;
		margin-top: 0;
		cursor: grab;
		background-color: var(--color-main-background);

		// Smooth fade out of the cards at the top
		&:before {
			content: ' ';
			display: block;
			position: absolute;
			width: calc(100% - 16px);
			height: 20px;
			top: 30px;
			left: 0px;
			z-index: 99;
			transition: top var(--animation-slow);

			background-image: linear-gradient(180deg, var(--color-main-background) 3px, rgba(255, 255, 255, 0) 100%);
			body.theme--dark & {
				background-image: linear-gradient(180deg, var(--color-main-background) 3px, rgba(0, 0, 0, 0) 100%);
			}
		}

		& > * {
			position: relative;
			z-index: 100;
		}

		h3, form {
			flex-grow: 1;
			display: flex;
			cursor: inherit;
			margin: 0;

			input[type=text] {
				flex-grow: 1;
			}
		}

		h3.stack__title {
			white-space: nowrap;
			overflow: hidden;
			text-overflow: ellipsis;
			max-width: calc($stack-width - 60px);
			border-radius: 3px;
			padding: 4px 4px;
			font-size: var(--default-font-size);

			&:focus-visible {
				outline: 2px solid var(--color-border-dark);
				border-radius: 3px;
			}
		}

		form {
			margin: -4px;
			input {
				font-weight: bold;
				padding: 0 6px;
			}
			input[type="submit"] {
				border-style: solid;
				border-left-style: none;
			}
		}

		:deep {
			.action-item,
			.v-popper--theme-dropdown {
				display: flex;
			}
		}
	}

	.stack__card-add {
		flex-shrink: 0;
		z-index: 100;
		display: flex;
		margin-bottom: 5px;
		padding-top: var(--default-grid-baseline);
		background-color: var(--color-main-background);

		form {
			display: flex;
			margin-left: $stack-spacing;
			margin-right: $stack-spacing;
			width: 100%;
			border: 2px solid var(--color-border-maxcontrast);
			border-radius: var(--border-radius-large);
			overflow: hidden;
			padding: 2px;
		}

		&.icon-loading-small:after,
		&.icon-loading-small-dark:after {
			margin-left: calc(50% - 25px);
		}

		input[type=text] {
			flex-grow: 1;
		}

		input {
			border: none;
			margin: 0;
			padding: 4px;
		}
	}

	/**
	 * Rules to handle scrolling behaviour are inherited from Board.vue
	 */

	.slide-top-enter-active,
	.slide-top-leave-active {
		transition: all 100ms ease;
	}

	.slide-top-enter, .slide-top-leave-to {
		transform: translateY(-10px);
		opacity: 0;
	}

	.slide-bottom-enter-active,
	.slide-bottom-leave-active {
		transition: all 100ms ease;
	}

	.slide-bottom-enter, .slide-bottom-leave-to {
		transform: translateY(20px);
		opacity: 0;
	}

	.modal__content {
		width: 25vw;
		min-width: 250px;
		min-height: 100px;
		text-align: center;
		margin: 20px 20px 20px 20px;
	}

	.modal__content button {
		float: right;
	}

	progress {
		margin-top: 3px;
		margin-bottom: 30px;
	}

</style>
