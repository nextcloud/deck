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
			board {{ board.title }}<br>
			<!-- example for external drop zone -->
			<container :should-accept-drop="() => true" style="border:1px solid #aaa;" />
			<button @click="toggleSidebar">toggle sidebar</button>
			<container lock-axix="y" orientation="horizontal" @drop="onDropStack">
				<draggable v-for="stack in stacks" :key="stack.id" class="stack">
					<h3>{{ stack.title }}</h3>
					<Container group-name="stack">
						<Draggable v-for="card in stack.cards"><card :id="card" @drop="onDropCard" /></Draggable>
					</Container>
				</draggable>
			</container>
		</div>
	</div>
</template>

<script>

import { Container, Draggable } from 'vue-smooth-dnd'
import { mapState } from 'vuex'
import Controls from '../Controls'
import Card from '../card/Card'

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
		title: 'card ' + i
	}
}

export default {
	name: 'Board',
	components: {
		Card,
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
			//return (stack) => _.orderBy(this.stacks[stack].cards, 'order')
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
		toggleSidebar: function() {
			this.$store.dispatch('toggleSidebar')
		},
		onDropStack(dropResult) {
			this.stacks = applyDrag(this.stacks, dropResult)
		}
	}
}
</script>

<style scoped>

</style>
