<!--
	- SPDX-FileCopyrightText: 2018 Nextcloud GmbH and Nextcloud contributors
	- SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<div class="stack"
		:class="{
			'stack--done-column': isDoneColumn,
			'stack--add-card-at-top': canAddCard && stackAddCardAtTop,
			'stack--add-card-at-bottom': canAddCard && !stackAddCardAtTop,
		}"
		:data-cy-stack="stack.title">
		<div class="stack__header"
			:class="{'stack__header--done-column': isDoneColumn}"
			:aria-label="stack.title">
			<transition name="fade" mode="out-in">
				<h3 v-if="!canManage || isArchived" tabindex="0">
					{{ stack.title }}
					<CheckCircleOutline v-if="isDoneColumn"
						class="stack__done-icon"
						decorative />
					<span class="stack__card-count">{{ cardsByStack.length }}</span>
				</h3>
				<h3 v-else-if="!editing"
					tabindex="0"
					:aria-label="stack.title"
					:title="stack.title"
					class="stack__title"
					@click="startEditing(stack)"
					@keydown.enter="startEditing(stack)">
					<span dir="auto">{{ stack.title }}</span>
					<CheckCircleOutline v-if="isDoneColumn"
						class="stack__done-icon"
						decorative />
					<span class="stack__card-count">{{ cardsByStack.length }}</span>
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
				<NcActionButton close-after-click @click="toggleDoneColumn">
					<template #icon>
						<CheckCircleOutline decorative />
					</template>
					{{ isDoneColumn ? t('deck', 'Do not set cards as "done"') : t('deck', 'Set cards as "done"') }}
				</NcActionButton>
				<NcActionButton icon="icon-delete" @click="deleteStackShowUndo(stack)">
					{{ t('deck', 'Delete list') }}
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

		<StackCardAdd v-if="canAddCard && stackAddCardAtTop"
			:stack="stack"
			:add-at-top="true"
			@creating="animate = true"
			@created="handleCardCreated" />

		<Container :get-child-payload="payloadForCard(stack.id)"
			class="dnd-container"
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

		<StackCardAdd v-if="canAddCard && !stackAddCardAtTop"
			:stack="stack"
			@creating="animate = true"
			@created="handleCardCreated" />
	</div>
</template>

<script>
import ClickOutside from 'vue-click-outside'
import { mapState, mapActions } from 'pinia'
import { Container, Draggable } from 'vue-smooth-dnd'
import ArchiveIcon from 'vue-material-design-icons/ArchiveOutline.vue'
import CheckCircleOutline from 'vue-material-design-icons/CheckCircleOutline.vue'
import { NcActions, NcActionButton, NcModal } from '@nextcloud/vue'
import { showUndo } from '@nextcloud/dialogs'

import CardItem from '../cards/CardItem.vue'
import StackCardAdd from './StackCardAdd.vue'

import '@nextcloud/dialogs/style.css'
import { useTrashbinStore } from '../../stores/trashbin.js'
import { useStackStore } from '../../stores/stack.js'
import { useCardStore } from '../../stores/card.js'
import { useBoardStore } from '../../stores/board.js'

export default {
	name: 'Stack',
	components: {
		NcActions,
		NcActionButton,
		CardItem,
		StackCardAdd,
		Container,
		Draggable,
		NcModal,
		ArchiveIcon,
		CheckCircleOutline,
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
			animate: false,
			modalArchivAllCardsShow: false,
			stackTransfer: {
				total: 0,
				current: null,
			},
		}
	},
	computed: {
		...mapState(useBoardStore, [
			'canManage',
			'canEdit',
			'isArchived',
			'showArchived',
		]),
		...mapState(useCardStore, {
			cardsByStackGetter: 'cardsByStack',
		}),
		cardsByStack() {
			return this.cardsByStackGetter(this.stack.id).filter((card) => {
				if (this.showArchived) {
					return card.archived
				}
				return !card.archived
			})
		},
		isDoneColumn() {
			return !!this.stack.isDoneColumn
		},
		dragHandleSelector() {
			return this.canEdit && !this.showArchived ? null : '.no-drag'
		},
		stackAddCardAtTop() {
			return this.$store.getters.config('stackAddCardAtTop') === true
		},
		canAddCard() {
			return this.canEdit && !this.showArchived && !this.isArchived
		},
	},
	mounted() {
		this.setupAutoscrollOnDrag()
	},

	methods: {
		...mapActions(useTrashbinStore, ['stackUndoDelete']),
		...mapActions(useStackStore, ['setDoneStack', 'deleteStack', 'updateStack']),
		...mapActions(useCardStore, {
			reorderCardInStore: 'reorderCard',
			archiveUnarchiveCardInStore: 'archiveUnarchiveCard',
			addCardInStore: 'addCard',
		}),
		async onDropCard(stackId, event) {
			const { addedIndex, removedIndex, payload } = event
			const card = Object.assign({}, payload)
			if (this.stack.id === stackId) {
				if (addedIndex !== null && removedIndex === null) {
					// move card to new stack
					card.stackId = stackId
					card.order = addedIndex
					console.debug('move card to stack', card.stackId, card.order)
					await this.reorderCardInStore(card)
				}
				if (addedIndex !== null && removedIndex !== null) {
					card.order = addedIndex
					console.debug('move card in stack', card.stackId, card.order)
					await this.reorderCardInStore(card)
				}
			}
		},
		payloadForCard(stackId) {
			return index => {
				return this.cardsByStack[index]
			}
		},
		toggleDoneColumn() {
			this.setDoneStack({
				stackId: this.stack.id,
				boardId: this.stack.boardId,
				isDone: !this.isDoneColumn,
			})
		},
		deleteStackShowUndo(stack) {
			this.deleteStack(stack)
			showUndo(t('deck', 'List deleted'), () => this.stackUndoDelete(stack))
		},
		setArchivedToAllCardsFromStack(stack, isArchived) {
			this.stackTransfer.total = this.cardsByStack.length
			this.cardsByStack.forEach((card, index) => {
				this.stackTransfer.current = index
				this.archiveUnarchiveCardInStore({ ...card, archived: isArchived })
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
				this.updateStack(this.copiedStack)
			}
			this.editing = false
		},
		cancelEdit() {
			this.editing = false
		},
		handleCardCreated(newCard) {
			this.$nextTick(() => {
				this.animate = false
				// Refs of a v-for are registered in creation order, not in list order
				this.$refs.card?.find((card) => card.id === newCard.id)?.scrollIntoView()
			})
		},
		setupAutoscrollOnDrag() {
			let timer
			const autoscroll = (event) => {
				const viewportX = event.clientX
				const boardElement = document.querySelector('.board')
				const viewportWidth = boardElement.clientWidth
				const offset = viewportWidth - viewportX
				const scrollMultiplier = 10
				const scrollBoundary = window.innerWidth * 0.15

				if (offset < 100) {
					const scrollToX = boardElement.scrollLeft + scrollMultiplier * (1 - offset / scrollBoundary)
					boardElement.scrollTo(scrollToX, boardElement.scrollTop)
				}

				if (boardElement.scrollLeft > 0 && viewportX < scrollBoundary) {
					const scrollToX = boardElement.scrollLeft - scrollMultiplier * (1 - viewportX / scrollBoundary)
					boardElement.scrollTo(scrollToX, boardElement.scrollTop)
				}
			}
			window.addEventListener('mousemove', (e) => {
				if (!this.draggingCard) {
					timer && clearInterval(timer)
					return
				}

				clearInterval(timer)
				timer = window.setInterval(() => autoscroll(e), 25)
			})
		},
	},
}
</script>

<style lang="scss" scoped>

	@use 'sass:math';

	@import './../../css/variables.scss';

	.stack {
		--stack-card-add-control-height: calc(var(--default-clickable-area) + 2 * var(--default-grid-baseline));
		width: 100%;
		.dnd-container {
			flex: 1 1 auto;
			min-height: 0;
		}

		&.stack--add-card-at-top {
			&:after {
				content: '';
				display: block;
				position: absolute;
				width: 100%;
				height: $stack-gap;
				bottom: 0;
				z-index: 99;
				pointer-events: none;
				background-image: linear-gradient(0deg, var(--color-main-background) 0%, transparent 100%);
			}
		}

		&.stack--done-column {
			.stack__header--done-column {
				border-bottom: 2px solid var(--color-success);
			}
		}
	}

	.stack__header {
		display: flex;
		position: sticky;
		top: 0;
		height: var(--default-clickable-area);
		z-index: 100;
		margin-top: 0;
		cursor: grab;
		background-color: var(--color-main-background);

		// Smooth fade out of the cards at the top
		&:before {
			content: '';
			display: block;
			position: absolute;
			width: 100%;
			height: $stack-gap;
			bottom: 0;
			z-index: 99;
			transition: top var(--animation-slow);
			background-image: linear-gradient(180deg, var(--color-main-background) 0%, transparent 100%);
			transform: translateY(100%);
		}

		& > * {
			position: relative;
			z-index: 100;
		}

		h3, form {
			flex: 1 1 auto;
			min-width: 0;
			display: flex;
			align-items: center;
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
			border-radius: 3px;
			padding: $card-padding;
			font-size: var(--default-font-size);

			span {
				overflow: hidden;
				text-overflow: ellipsis;
			}

			&:focus-visible {
				outline: 2px solid var(--color-border-dark);
				border-radius: 3px;
			}
		}

		.stack__done-icon {
			flex-shrink: 0;
			color: var(--color-main-text);
			margin-inline-start: 2px;
			width: 1em;
			height: 1em;

			:deep(svg) {
				width: 1em;
				height: 1em;
			}
		}

		.stack__card-count {
			flex-shrink: 0;
			margin-inline-start: 6px;
			padding: 0 8px;
			border-radius: var(--border-radius-pill, 16px);
			background-color: var(--color-background-darker);
			color: var(--color-text-maxcontrast);
			font-size: var(--default-font-size);
			font-weight: normal;
			line-height: 1.5;
		}

		form {
			input {
				font-weight: bold;
				padding: 0 6px;
			}
			input[type="submit"] {
				border-style: solid;
				border-inline-start-style: none;
			}
		}

		:deep {
			.action-item,
			.v-popper--theme-dropdown {
				display: flex;
			}
		}
	}

	.modal__content {
		width: 25vw;
		min-width: 250px;
		min-height: 100px;
		text-align: center;
		margin: 20px 20px 20px 20px;
	}

	.modal__content button {
		float: inline-end;
	}

	progress {
		margin-top: 3px;
		margin-bottom: 30px;
	}

</style>
