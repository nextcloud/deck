<!--
  - SPDX-FileCopyrightText: 2018 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<div class="board-wrapper" :tabindex="-1" @touchend="fixActionRestriction">
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
						<input title="t('deck', 'Add list')"
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
		<GlobalSearchResults v-if="isFullApp" />
		<NcModal v-if="localModal"
			:clear-view-delay="0"
			:close-button-contained="true"
			size="large"
			@close="localModal = null">
			<div class="modal__content modal__card">
				<CardSidebar :id="localModal" @close="localModal = null" />
			</div>
		</NcModal>
	</div>
</template>

<script>
import { Container, Draggable } from 'vue-smooth-dnd'
import { mapState, mapGetters } from 'vuex'
import Controls from '../Controls.vue'
import DeckIcon from '../icons/DeckIcon.vue'
import Stack from './Stack.vue'
import { NcEmptyContent, NcModal } from '@nextcloud/vue'
import GlobalSearchResults from '../search/GlobalSearchResults.vue'
import { showError } from '../../helpers/errors.js'
import { createSession } from '../../sessions.js'
import CardSidebar from '../card/CardSidebar.vue'
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
		NcModal,
		CardSidebar,
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
			localModal: null,
		}
	},
	computed: {
		...mapState({
			isFullApp: state => state.isFullApp,
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
		this.$root.$on('open-card', (cardId) => {
			this.localModal = cardId
		})
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

		fixActionRestriction() {
			document.body.classList.remove(
				'smooth-dnd-no-user-select',
				'smooth-dnd-disable-touch-action',
			)
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
		max-height: calc(100% - var(--default-clickable-area));
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
					// Margin left instead of padidng to avoid jumps on dropping a card
					margin-left: $stack-spacing;
					padding-right: $stack-spacing;
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
