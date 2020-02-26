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
		<div class="stack--header">
			<transition name="fade" mode="out-in">
				<h3 v-if="!canManage">
					{{ stack.title }}
				</h3>
				<h3 v-else-if="!editing" @click="startEditing(stack)">
					{{ stack.title }}
				</h3>
				<form v-else @submit.prevent="finishedEdit(stack)">
					<input v-model="copiedStack.title" type="text" autofocus>
					<input v-tooltip="t('deck', 'Add a new stack')"
						class="icon-confirm"
						type="submit"
						value="">
				</form>
			</transition>
			<Actions v-if="canManage" :force-menu="true">
				<ActionButton icon="icon-delete" @click="deleteStack(stack)">
					{{ t('deck', 'Delete list') }}
				</ActionButton>
			</Actions>
			<Actions v-if="canEdit">
				<ActionButton icon="icon-add" @click="showAddCard=true">
					{{ t('deck', 'Add card') }}
				</ActionButton>
			</Actions>
		</div>

		<form v-if="showAddCard" class="stack--card-add" @submit.prevent="clickAddCard()">
			<label for="new-stack-input-main" class="hidden-visually">{{ t('deck', 'Add a new card') }}</label>
			<input id="new-stack-input-main"
				v-model="newCardTitle"
				v-focus
				type="text"
				class="no-close"
				placeholder="Add a new card"
				required>

			<input class="icon-confirm"
				type="submit"
				value="">
		</form>

		<Container :get-child-payload="payloadForCard(stack.id)"
			group-name="stack"
			non-drag-area-selector=".dragDisabled"
			:drag-handle-selector="dragHandleSelector"
			@should-accept-drop="canEdit"
			@drop="($event) => onDropCard(stack.id, $event)">
			<Draggable v-for="card in cardsByStack(stack.id)" :key="card.id">
				<CardItem v-if="card" :id="card.id" />
			</Draggable>
		</Container>
	</div>
</template>

<script>

import { mapGetters } from 'vuex'
import { Container, Draggable } from 'vue-smooth-dnd'
import { Actions, ActionButton } from '@nextcloud/vue'
import CardItem from '../cards/CardItem'

export default {
	name: 'Stack',
	components: {
		Actions,
		ActionButton,
		CardItem,
		Container,
		Draggable,
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
			copiedStack: '',
			newCardTitle: '',
			showAddCard: false,
		}
	},
	computed: {
		...mapGetters([
			'canManage',
			'canEdit',
		]),
		cardsByStack() {
			return (id) => this.$store.getters.cardsByStack(id)
		},
		dragHandleSelector() {
			return this.canEdit ? null : '.no-drag'
		},
	},

	methods: {
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
				return this.cardsByStack(stackId)[index]
			}
		},
		deleteStack(stack) {
			this.$store.dispatch('deleteStack', stack)
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
		clickAddCard() {
			const newCard = {
				title: this.newCardTitle,
				stackId: this.stack.id,
				boardId: this.stack.boardId,
			}
			this.$store.dispatch('addCard', newCard)
			this.newCardTitle = ''
			this.showAddCard = false
		},
	},
}
</script>

<style lang="scss" scoped>

	$stack-spacing: 10px;
	$stack-width: 260px;

	.stack {
		width: $stack-width;
		margin-left: $stack-spacing;
		margin-right: $stack-spacing;
	}

	.stack--header {
		display: flex;
		position: sticky;
		top: 0;
		z-index: 100;
		padding: 3px;
		margin: 3px -3px;
		margin-right: -10px;
		margin-bottom: 5px;
		margin-top: 0;
		background-color: var(--color-main-background-translucent);

		h3, form {
			flex-grow: 1;
			display: flex;

			input[type=text] {
				flex-grow: 1;
			}
		}
	}

	.stack--card-add {
		display: flex;
		margin-left: 3px;
		margin-right: 3px;
		box-shadow: 0 0 3px #aaa;
		border-radius: 3px;
		margin-bottom: 15px;

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

</style>
