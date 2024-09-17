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
	<div class="board-wrapper" :tabindex="-1">
		<Controls :board="board" />

		<transition name="fade" mode="out-in">
			<div v-if="loading" key="loading" class="emptycontent">
				<div class="icon icon-loading" />
				<h2>{{ t('deck', 'Loading board') }}</h2>
				<p />
			</div>
			<div v-else-if="!board" key="notfound" class="emptycontent">
				<div class="icon icon-deck" />
				<h2>{{ t('deck', 'Board not found') }}</h2>
				<p />
			</div>
			<NcEmptyContent v-else-if="isEmpty" key="empty">
				<template #icon>
					<DeckIcon />
				</template>
				<template #title>
					{{ t('deck', 'No lists available') }}
				</template>
				<template v-if="canManage" #action>
					{{ t('deck', 'Create a new list to add cards to this board') }}
					<form @submit.prevent="addNewStack()">
						<input id="new-stack-input-main"
							v-model="newStackTitle"
							v-focus
							type="text"
							class="no-close"
							:placeholder="t('deck', 'List name')"
							required>
						<input v-tooltip="t('deck', 'Add list')"
							class="icon-confirm"
							type="submit"
							value="">
					</form>
				</template>
			</NcEmptyContent>
			<div v-else-if="!isEmpty && !loading"
				key="board"
				ref="board"
				class="board"
				@mousedown="onMouseDown">
				<Container lock-axix="y"
					orientation="horizontal"
					:drag-handle-selector="dragHandleSelector"
					data-click-closes-sidebar="true"
					@drag-start="draggingStack = true"
					@drag-end="draggingStack = false"
					@drop="onDropStack">
					<Draggable v-for="stack in stacksByBoard"
						:key="stack.id"
						data-click-closes-sidebar="true"
						data-dragscroll-enabled
						class="stack-draggable-wrapper">
						<Stack :stack="stack" :dragging="draggingStack" data-click-closes-sidebar="true" />
					</Draggable>
				</Container>
			</div>
		</transition>
		<GlobalSearchResults />
	</div>
</template>

<script>
import { Container, Draggable } from 'vue-smooth-dnd'
import { mapState, mapGetters } from 'vuex'
import Controls from '../Controls.vue'
import DeckIcon from '../icons/DeckIcon.vue'
import Stack from './Stack.vue'
import { NcEmptyContent } from '@nextcloud/vue'
import GlobalSearchResults from '../search/GlobalSearchResults.vue'
import { showError } from '../../helpers/errors.js'
import { createSession } from '../../sessions.js'

export default {
	name: 'Board',
	components: {
		GlobalSearchResults,
		Controls,
		Container,
		DeckIcon,
		Draggable,
		Stack,
		NcEmptyContent,
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
			draggingStack: false,
			loading: true,
			newStackTitle: '',
			currentScrollPosX: null,
			currentMousePosX: null,
		}
	},
	computed: {
		...mapState({
			board: state => state.currentBoard,
			showArchived: state => state.showArchived,
		}),
		...mapGetters([
			'canEdit',
			'canManage',
		]),
		stacksByBoard() {
			return this.$store.getters.stacksByBoard(this.board.id)
		},
		dragHandleSelector() {
			return this.canEdit ? '.stack__title' : '.no-drag'
		},
		isEmpty() {
			return this.stacksByBoard.length === 0
		},
	},
	watch: {
		id(newValue, oldValue) {
			this.fetchData()
		},
		showArchived() {
			this.fetchData()
		},
	},
	created() {
		this.session = createSession(this.id)
		this.fetchData()
	},
	beforeDestroy() {
		this.session.close()
	},
	methods: {
		async fetchData() {
			this.loading = true
			try {
				await this.$store.dispatch('loadBoardById', this.id)
				await this.$store.dispatch('loadStacks', this.id)

				const routeCardId = parseInt(this.$route.params.cardId)
				// If an archived card is requested, and we cannot find it in the current we load the archived stacks instead
				if (routeCardId && !this.$store.getters.cardById(routeCardId)) {
					await this.$store.dispatch('loadArchivedStacks', this.id)

					if (this.$store.getters.cardById(routeCardId)) {
						this.$store.commit('toggleShowArchived', true)
					}
				}

				this.session?.close()
				this.session = createSession(this.id)
			} catch (e) {
				this.loading = false
				console.error(e)
				showError(e)
			} finally {
				this.loading = false
			}
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

		onMouseDown(event) {
			this.startMouseDrag(event)
		},

		startMouseDrag(event) {
			if (!('dragscrollEnabled' in event.target.dataset)) {
				return
			}

			event.preventDefault()
			this.currentMousePosX = event.clientX
			this.currentScrollPosX = this.$refs.board.scrollLeft
			window.addEventListener('mousemove', this.handleMouseDrag)
			window.addEventListener('mouseup', this.stopMouseDrag)
			window.addEventListener('mouseleave', this.stopMouseDrag)
		},

		handleMouseDrag(event) {
			event.preventDefault()
			const deltaX = event.clientX - this.currentMousePosX
			this.$refs.board.scrollLeft = this.currentScrollPosX - deltaX
		},

		stopMouseDrag(event) {
			window.removeEventListener('mousemove', this.handleMouseDrag)
			window.removeEventListener('mouseup', this.stopMouseDrag)
			window.removeEventListener('mouseleave', this.stopMouseDrag)
		},
	},
}
</script>

<style lang="scss" scoped>
	@import '../../css/animations';
	@import '../../css/variables';

	form {
		text-align: center;
		display: flex;
		width: 100%;
		max-width: 200px;
		margin: auto;
		margin-top: 20px;

		input[type=text] {
			flex-grow: 1;
		}
	}

	.board-wrapper {
		position: relative;
		width: 100%;
		height: 100%;
		max-height: calc(100vh - 50px);
		display: flex;
		flex-direction: column;
	}

	.board {
		padding-left: $board-spacing;
		position: relative;
		max-height: calc(100% - 44px);
		overflow: hidden;
		overflow-x: auto;
		flex-grow: 1;
	}

	/**
	 * Combined rules to handle proper board scrolling and
	 * drag and drop behavior
	 */
	.smooth-dnd-container.horizontal {
		display: flex;
		align-items: stretch;
		height: 100%;

		&:deep(.stack-draggable-wrapper.smooth-dnd-draggable-wrapper) {
			display: flex;
			height: auto;

			.stack {
				display: flex;
				flex-direction: column;
				position: relative;

				.smooth-dnd-container.vertical {
					flex-grow: 1;
					display: flex;
					flex-direction: column;
					padding: $stack-spacing;
					overflow-x: hidden;
					overflow-y: auto;
					padding-top: 15px;
					margin-top: -10px;
					scrollbar-gutter: stable;
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
