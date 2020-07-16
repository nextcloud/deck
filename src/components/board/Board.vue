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
	<div class="board-wrapper">
		<Controls :board="board" />

		<EmptyContent v-if="stacksByBoard.length === 0" icon="icon-pause">
			{{ t('deck', 'No lists, add one') }}
			<template #desc>
				<form @submit.prevent="addNewStack()">
					<input id="new-stack-input-main"
						v-model="newStackTitle"
						v-focus
						type="text"
						class="no-close"
						:placeholder="t('deck', 'List name')"
						required>
					<input v-tooltip="t('deck', 'Add new list')"
						class="icon-confirm"
						type="submit"
						value="">
				</form>
			</template>
		</EmptyContent>

		<transition name="fade" mode="out-in">
			<div v-if="loading" key="loading" class="emptycontent">
				<div class="icon icon-loading" />
				<h2>{{ t('deck', 'Loading board') }}</h2>
				<p />
			</div>
			<div v-else-if="board && !loading" key="board" class="board">
				<Container lock-axix="y"
					orientation="horizontal"
					:drag-handle-selector="dragHandleSelector"
					@drop="onDropStack">
					<Draggable v-for="stack in stacksByBoard" :key="stack.id">
						<Stack :stack="stack" />
					</Draggable>
				</Container>
			</div>
			<div v-else key="notfound" class="emptycontent">
				<div class="icon icon-deck" />
				<h2>{{ t('deck', 'Board not found') }}</h2>
				<p />
			</div>
		</transition>
	</div>
</template>

<script>

import { Container, Draggable } from 'vue-smooth-dnd'
import { mapState, mapGetters } from 'vuex'
import Controls from '../Controls'
import Stack from './Stack'
import { EmptyContent } from '@nextcloud/vue'

export default {
	name: 'Board',
	components: {
		Controls,
		Container,
		Draggable,
		Stack,
		EmptyContent,
	},
	inject: [
		'boardApi',
	],
	props: {
		id: {
			type: Number,
			default: null,
		},
	},
	data() {
		return {
			loading: true,
			newStackTitle: '',
		}
	},
	computed: {
		...mapState({
			board: state => state.currentBoard,
			showArchived: state => state.showArchived,
		}),
		...mapGetters([
			'canEdit',
		]),
		stacksByBoard() {
			return this.$store.getters.stacksByBoard(this.board.id)
		},
		dragHandleSelector() {
			return this.canEdit ? null : '.no-drag'
		},
	},
	watch: {
		id: 'fetchData',
		showArchived() {
			this.fetchData()
		},
	},
	created() {
		this.fetchData()
	},
	methods: {
		async fetchData() {
			this.loading = true
			try {
				await this.$store.dispatch('loadBoardById', this.id)
				await this.$store.dispatch('loadStacks', this.id)
			} catch (e) {
				console.error(e)
			}
			this.loading = false
		},

		onDropStack({ removedIndex, addedIndex }) {
			this.$store.dispatch('orderStack', { stack: this.stacksByBoard[removedIndex], removedIndex, addedIndex })
		},

		addNewStack() {
			const newStack = {
				title: this.newStackTitle,
				boardId: this.id,
			}
			this.$store.dispatch('createStack', newStack)
			this.newStackTitle = ''
		},
	},
}
</script>

<style lang="scss" scoped>

	@import '../../css/animations.scss';

	$board-spacing: 15px;
	$stack-spacing: 10px;
	$stack-width: 300px;

	.board-wrapper {
		position: relative;
		width: 100%;
		height: 100%;
		max-height: calc(100vh - 50px);
	}

	.board {
		margin-left: $board-spacing;
		position: relative;
		height: calc(100% - 44px);
		overflow-x: scroll;
	}

	/**
	 * Combined rules to handle proper board scrolling and
	 * drag and drop behavior
	 */
	.smooth-dnd-container.horizontal {
		display: flex;
		align-items: stretch;
		.smooth-dnd-draggable-wrapper::v-deep {
			display: flex;
			height: auto;

			.stack {
				display: flex;
				flex-direction: column;

				.smooth-dnd-container.vertical {
					flex-grow: 1;
					display: flex;
					flex-direction: column;
					padding: 0;
					/**
					 * Use this to scroll each stack individually
					 * This currenly has the issue that the popover menu will be cut off
					 */
					/*
					overflow-x: scroll;
					height: calc(100vh - 50px - 44px * 2 - 30px);
					max-height: calc(100vh - 50px - 44px * 2 - 30px);
					*/
				}

				.smooth-dnd-container.vertical > .smooth-dnd-draggable-wrapper {
					overflow: initial;
				}

				.smooth-dnd-container.vertical .smooth-dnd-draggable-wrapper {
					height: auto;
				}
			}
		}
	}

</style>
