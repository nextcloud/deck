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
				<h3 v-if="!editing" @click="startEditing(stack)">{{ stack.title }}</h3>
				<form v-else>
					<input v-model="copiedStack.title" type="text" autofocus>
					<input type="button" class="icon-confirm" @click="finishedEdit(stack)">
				</form>
			</transition>
			<Actions>
				<ActionButton icon="icon-delete" @click="deleteStack(stack)">{{ t('deck', 'Delete stack') }}</ActionButton>
			</Actions>
		</div>

		<!-- <container :get-child-payload="payloadForCard(stack.id)" group-name="stack" @drop="($event) => onDropCard(stack.id, $event)"> -->
		<container :get-child-payload="payloadForCard(stack.id)" group-name="stack" @drop="onDropCard">
			<draggable v-for="card in cardsByStack(stack.id)" :key="card.id">
				<card-item v-if="card" :id="card.id" />
			</draggable>
		</container>

		<form class="stack--card-add" @submit.prevent="clickAddCard()">
			<label for="new-stack-input-main" class="hidden-visually">Add a new card</label>
			<input id="new-stack-input-main" v-model="newCardTitle" type="text"
				class="no-close"
				placeholder="Add a new card">
			<input class="icon-confirm" type="submit" value="" v-tooltip="t('deck', 'Create a new card')">
		</form>

	</div>
</template>

<script>

import { Container, Draggable } from 'vue-smooth-dnd'
import { Actions } from 'nextcloud-vue/dist/Components/Actions'
import { ActionButton } from 'nextcloud-vue/dist/Components/ActionButton'
import CardItem from '../cards/CardItem'

export default {
	name: 'Stack',
	components: {
		Actions,
		ActionButton,
		CardItem,
		Container,
		Draggable
	},

	props: {
		stack: {
			type: Object,
			default: undefined
		}
	},
	data() {
		return {
			editing: false,
			copiedStack: '',
			newCardTitle: ''
		}
	},
	computed: {
		cardsByStack() {
			return (id) => this.$store.getters.cardsByStack(id)
		}
	},

	methods: {

		onDropCard({ removedIndex, addedIndex }) {
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
			let newCard = {
				title: this.newCardTitle,
				stackId: this.stack.id,
				boardId: this.stack.boardId
			}
			this.$store.dispatch('addCard', newCard)
		}
	}
}
</script>

<style lang="scss" scoped>

	$stack-spacing: 10px;
	$stack-width: 300px;

	.stack {
		width: $stack-width;
		padding: $stack-spacing;
		padding-top: 0;
	}

	.smooth-dnd-container.vertical {
		display: flex;
		flex-direction: column;
	}

	.smooth-dnd-container.vertical > .smooth-dnd-draggable-wrapper {
		overflow: initial;
	}

	.smooth-dnd-container.vertical .smooth-dnd-draggable-wrapper {
		height: auto;
	}

	.stack--header {
		display: flex;

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

		input[type=text] {
			flex-grow: 1;
		}
	}

</style>
