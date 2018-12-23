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
	<div>
		<Controls :board="board" />
		<div v-if="board">
			<!-- example for external drop zone -->
			<!-- <container :should-accept-drop="() => true" style="border:1px solid #aaa;" /> -->
			<container lock-axix="y" orientation="horizontal" @drop="onDropStack">
				<draggable v-for="stack in stacks" :key="stack.id" class="stack">
					<h3>{{ stack.title }}</h3>
					<container :get-child-payload="payload(stack.id)" group-name="stack" @drop="($event) => onDropCard(stack.id, $event)">
						<draggable v-for="card in stack.cards" :key="card.id">
							<card-item :id="card.id" />
						</draggable>
					</container>
				</draggable>
			</container>
		</div>
		<div v-else class="emptycontent">
			<div class="icon icon-deck"></div>
			<h2>{{ t('deck', 'Board not found')}}</h2>
			<p></p>
		</div>
	</div>
</template>

<script>

import { Container, Draggable } from 'vue-smooth-dnd'
import { mapState } from 'vuex'
import Controls from '../Controls'
import CardItem from '../cards/CardItem'

const applyDrag = (arr, dragResult) => {
	const { removedIndex, addedIndex, payload } = dragResult
	if (removedIndex === null && addedIndex === null) return arr

	const result = [...arr]
	let itemToAdd = payload

	if (removedIndex !== null) {
		itemToAdd = result.splice(removedIndex, 1)[0]
	}

	if (addedIndex !== null) {
		result.splice(addedIndex, 0, itemToAdd)
	}

	return result
}

const dummyCard = function(i) {
	return {
		id: i,
		order: 0,
		title: 'card ' + i,
		stackId: 1
	}
}

export default {
	name: 'Board',
	components: {
		CardItem,
		Controls,
		Container,
		Draggable
	},
	inject: [
		'boardApi'
	],
	props: {
		id: {
			type: Number,
			default: null
		}
	},
	data: function() {
		return {
			loading: true,
			stacks: [
				{ id: 1, title: 'abc', cards: [dummyCard(1), dummyCard(2), dummyCard(3), dummyCard(4), dummyCard(5)] },
				{ id: 2, title: '234', cards: [dummyCard(6), dummyCard(7)] }
			]
		}
	},
	computed: {
		...mapState({
			board: state => state.currentBoard
		}),
		orderedCards() {
			// return (stack) => _.orderBy(this.stacks[stack].cards, 'order')
		}

	},
	created: function() {
		this.boardApi.loadById(this.id)
			.then((board) => {
				this.$store.dispatch('setCurrentBoard', board)
				this.loading = false
			})
	},
	methods: {
		onDropStack(dropResult) {
			// TODO: persist new order in order field
			this.stacks = applyDrag(this.stacks, dropResult)
		},
		onDropCard(stackId, dropResult) {
			if (dropResult.removedIndex !== null || dropResult.addedIndex !== null) {
				// TODO: persist new order in order field
				const stacks = this.stacks
				const stack = stacks.filter(p => p.id === stackId)[0]
				const stackIndex = stacks.indexOf(stack)
				const newStack = Object.assign({}, stack)
				newStack.cards = applyDrag(newStack.cards, dropResult)
				stacks.splice(stackIndex, 1, newStack)
				this.stacks = stacks
			}
		},
		payload(stackId) {
			return index => {
				return this.stacks.find(stack => stack.id === stackId).cards[index]
			}
		}
	}
}
</script>

<style scoped>

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

</style>
