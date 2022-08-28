<!--
  - @copyright Copyright (c) 2018 Julius Härtl <jus@bitgrid.net>
  -
  - @author Julius Härtl <jus@bitgrid.net>
  - @author Jakob Röhrl <jakob.roehrl@web.de>
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
	<div class="stack">
		<div v-click-outside="stopCardCreation"
			class="stack__header"
			:class="{'stack__header--add': showAddCard }"
			tabindex="0"
			:aria-label="stack.title">
			<transition name="fade" mode="out-in">
				<h3 v-if="!canManage || isArchived">
					{{ stack.title }}
				</h3>
				<h3 v-else-if="!editing"
					v-tooltip="stack.title"
					tabindex="0"
					:aria-label="stack.title"
					class="stack__title"
					@click="startEditing(stack)"
					@keydown.enter="startEditing(stack)">
					{{ stack.title }}
				</h3>
				<form v-else @submit.prevent="finishedEdit(stack)">
					<input v-model="copiedStack.title"
						v-focus
						type="text"
						required="required">
					<input v-tooltip="t('deck', 'Add a new list')"
						class="icon-confirm"
						type="submit"
						value="">
				</form>
			</transition>
			<NcActions v-if="canManage && !isArchived" :force-menu="true">
				<NcActionButton @click="modalArchivAllCardsShow=true">
					<template #icon>
						<ArchiveIcon decorative />
					</template>
					{{ t('deck', 'Archive all cards') }}
				</NcActionButton>
				<NcActionButton icon="icon-delete" @click="deleteStack(stack)">
					{{ t('deck', 'Delete list') }}
				</NcActionButton>
			</NcActions>
			<NcActions v-if="canEdit && !showArchived && !isArchived">
				<NcActionButton icon="icon-add" @click.stop="showAddCard=true">
					{{ t('deck', 'Add card') }}
				</NcActionButton>
			</NcActions>
		</div>

		<NcModal v-if="modalArchivAllCardsShow" @close="modalArchivAllCardsShow=false">
			<div class="modal__content">
				<h3>{{ t('deck', 'Archive all cards in this list') }}</h3>
				<progress :value="stackTransfer.current" :max="stackTransfer.total" />
				<button class="primary" @click="archiveAllCardsFromStack(stack)">
					{{ t('deck', 'Archive all cards') }}
				</button>
				<button @click="modalArchivAllCardsShow=false">
					{{ t('deck', 'Cancel') }}
				</button>
			</div>
		</NcModal>

		<transition name="slide-top" appear>
			<div v-if="showAddCard" class="stack__card-add">
				<form :class="{ 'icon-loading-small': stateCardCreating }"
					@submit.prevent.stop="clickAddCard()">
					<label for="new-stack-input-main" class="hidden-visually">{{ t('deck', 'Add a new card') }}</label>
					<input id="new-stack-input-main"
						ref="newCardInput"
						v-model="newCardTitle"
						v-focus
						type="text"
						class="no-close"
						:disabled="stateCardCreating"
						:placeholder="t('deck', 'Card name')"
						required
						pattern=".*\S+.*"
						@keydown.esc="stopCardCreation">
					<input v-show="!stateCardCreating"
						class="icon-confirm"
						type="submit"
						value="">
				</form>
			</div>
		</transition>

		<Container :get-child-payload="payloadForCard(stack.id)"
			group-name="stack"
			non-drag-area-selector=".dragDisabled"
			:drag-handle-selector="dragHandleSelector"
			@should-accept-drop="canEdit"
			@drag-start="draggingCard = true"
			@drag-end="draggingCard = false"
			@drop="($event) => onDropCard(stack.id, $event)">
			<Draggable v-for="card in cardsByStack" :key="card.id">
				<transition :appear="animate && !card.animated && (card.animated=true)"
					:appear-class="'zoom-appear-class'"
					:appear-active-class="'zoom-appear-active-class'">
					<CardItem :id="card.id" :dragging="draggingCard" />
				</transition>
			</Draggable>
		</Container>
	</div>
</template>

<script>

import { mapGetters, mapState } from 'vuex'
import { Container, Draggable } from 'vue-smooth-dnd'

import { NcActions, NcActionButton, NcModal } from '@nextcloud/vue'
import { showError, showUndo } from '@nextcloud/dialogs'
import CardItem from '../cards/CardItem'

import '@nextcloud/dialogs/styles/toast.scss'
import ArchiveIcon from 'vue-material-design-icons/Archive'

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
	},

	props: {
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
			return this.canEdit ? null : '.no-drag'
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
		archiveAllCardsFromStack(stack) {

			this.stackTransfer.total = this.cardsByStack.length
			this.cardsByStack.forEach((card, index) => {
				this.stackTransfer.current = index
				this.$store.dispatch('archiveUnarchiveCard', { ...card, archived: true })
			})
			this.modalArchivAllCardsShow = false
		},
		startEditing(stack) {
			this.copiedStack = Object.assign({}, stack)
			this.editing = true
		},
		finishedEdit(stack) {
			if (this.copiedStack.title !== stack.title) {
				this.$store.dispatch('updateStack', this.copiedStack)
			}
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
	},
}
</script>

<style lang="scss" scoped>

	@use 'sass:math';

	@import './../../css/variables';

	.stack {
		width: $stack-width + $stack-spacing * 3;
		margin-left: math.div($stack-spacing, 2);
		margin-right: math.div($stack-spacing, 2);
	}

	.stack__header {
		display: flex;
		position: sticky;
		top: 0;
		z-index: 100;
		padding-left: $card-spacing;
		cursor: grab;
		min-height: 44px;

		// Smooth fade out of the cards at the top
		&:before {
			content: ' ';
			display: block;
			position: absolute;
			width: 100%;
			height: 20px;
			top: 30px;
			right: 10px;
			z-index: 99;
			transition: top var(--animation-slow);

			background-image: linear-gradient(180deg, var(--color-main-background) 3px, rgba(255, 255, 255, 0) 100%);
			body.theme--dark & {
				background-image: linear-gradient(180deg, var(--color-main-background) 3px, rgba(0, 0, 0, 0) 100%);
			}
		}

		&--add:before {
			height: 80px;
			background-image: linear-gradient(180deg, var(--color-main-background) 68px, rgba(255, 255, 255, 0) 100%);
			body.theme--dark & {
				background-image: linear-gradient(180deg, var(--color-main-background) 68px, rgba(0, 0, 0, 0) 100%);
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
			margin: 6px;
			padding: 4px 4px;

			&:focus {
				outline: 2px solid var(--color-border-dark);
				border-radius: 3px;
			}
		}

		form {
			margin: 2px 0;
		}
	}

	.stack__card-add {
		height: 44px;
		flex-shrink: 0;
		z-index: 100;
		display: flex;
		margin-top: 5px;
		margin-bottom: 20px;
		background-color: var(--color-main-background);

		form {
			display: flex;
			margin-left: 12px;
			margin-right: 12px;
			width: 100%;
			box-shadow: 0 0 3px var(--color-box-shadow);
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
		height: 0px;
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
