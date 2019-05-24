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
		<div v-if="board" class="board">
			<container lock-axix="y" orientation="horizontal" @drop="onDropStack">
				<draggable v-for="stack in stacksByBoard" :key="stack.id" class="stack">
					<stack :stack="stack" />
					<!-- <h3>{{ stack.title }}
						<button v-tooltip="t('deck', 'Delete')" class="icon-delete"
							@click="deleteStack(stack)" />
					</h3>
					<container :get-child-payload="payloadForCard(stack.id)" group-name="stack" @drop="($event) => onDropCard(stack.id, $event)">
						<draggable v-for="card in cardsByStack(stack.id)" :key="card.id">
							<card-item v-if="card" :id="card.id" />
						</draggable>
					</container>
					<div class="card create">
						<div title="Add card">
							<i class="icon icon-add" />
							<span class="hidden-visually">Add card</span>
						</div>
					</div>
 -->
				</draggable>
			</container>
		</div>
		<div v-else class="emptycontent">
			<div class="icon icon-deck" />
			<h2>{{ t('deck', 'Board not found') }}</h2>
			<p />
		</div>
	</div>
</template>

<script>

import { Container, Draggable } from 'vue-smooth-dnd'
import { mapState } from 'vuex'
import Controls from '../Controls'
/* import CardItem from '../cards/CardItem' */
import Stack from './Stack'

export default {
	name: 'Board',
	components: {
		/* CardItem, */
		Controls,
		Container,
		Draggable,
		Stack
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
			loading: true
		}
	},
	computed: {
		...mapState({
			board: state => state.currentBoard
		}),
		stacksByBoard() {
			return this.$store.getters.stacksByBoard(this.board.id)
		}
		/* cardsByStack() {
			return (id) => this.$store.getters.cardsByStack(id)
		} */
	},
	watch: {
		'$route': 'fetchData'
	},
	created() {
		this.fetchData()
	},
	methods: {
		fetchData() {
			this.boardApi.loadById(this.id)
				.then((board) => {
					this.$store.dispatch('setCurrentBoard', board)
					this.$store.dispatch('loadStacks', board)
					this.loading = false
					this.$store.state.labels = board.labels
				})
		},
		onDropStack({ removedIndex, addedIndex }) {
			this.$store.dispatch('orderStack', { stack: this.stacksByBoard[removedIndex], removedIndex, addedIndex })
		},
		/* onDropCard({ removedIndex, addedIndex }) {

		}, */
		/* payloadForCard(stackId) {
			return index => {
				return this.cardsByStack(stackId)[index]
			}
		}, */
		createStack() {
			let newStack = {
				title: 'FooBar',
				boardId: this.id,
				order: this.stacksByBoard().length
			}
			this.$store.dispatch('createStack', newStack)
		}
		/* deleteStack(stack) {
			this.$store.dispatch('deleteStack', stack)
		} */
	}
}
</script>

<style lang="scss" scoped>

	$board-spacing: 15px;
	$stack-spacing: 10px;
	$stack-width: 300px;

	.board {
		margin-left: $board-spacing;
	}

	.stack {
		width: $stack-width;
		padding: $stack-spacing;
		padding-top: 0;
	}

	/*
	.smooth-dnd-container.vertical {
		display: flex;
		flex-direction: column;
	}

	.smooth-dnd-container.vertical > .smooth-dnd-draggable-wrapper {
		overflow: initial;
	}

	.smooth-dnd-container.vertical .smooth-dnd-draggable-wrapper {
		height: auto;
	} */

</style>
