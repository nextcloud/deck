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
				<template #name>
					{{ t('deck', 'No lists available') }}
				</template>
				<template v-if="canManage" #action>
					{{ t('deck', 'Create a new list to add cards to this board') }}
					<form @submit.prevent="addNewStack()">
						<NcTextField ref="newStackInput"
							:disable="loading"
							:value.sync="newStackTitle"
							:placeholder="t('deck', 'List name')"
							type="text" />
						<NcButton type="secondary"
							native-type="submit"
							:disabled="loading"
							:title="t('deck', 'Add list')">
							<template #icon>
								<CheckIcon v-if="!loading" :size="20" />
								<NcLoadingIcon v-else :size="20" />
							</template>
							{{ t('deck', 'Add list') }}
						</NcButton>
					</form>
				</template>
			</NcEmptyContent>
			<GanttView v-else-if="!isEmpty && !loading && viewMode === 'gantt'"
				key="gantt"
				:board="board"
				:stacks="stacksByBoard" />
			<div v-else-if="!isEmpty && !loading && swimlaneMode === 'none'"
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
			<div v-else-if="!isEmpty && !loading && viewMode === 'kanban'"
				key="board-swimlanes"
				ref="board"
				role="region"
				:aria-label="t('deck', 'Board grouped by {mode}', { mode: swimlaneMode })"
				class="board board--swimlanes">
				<div class="board__stack-headers">
					<Container orientation="horizontal"
						:drag-handle-selector="dragHandleSelector"
						data-click-closes-sidebar="true"
						@drag-start="draggingStack = true"
						@drag-end="draggingStack = false"
						@drop="onDropStack">
						<Draggable v-for="stack in stacksByBoard"
							:key="stack.id"
							data-click-closes-sidebar="true"
							class="stack-draggable-wrapper">
							<Stack :stack="stack"
								:dragging="draggingStack"
								header-only
								data-click-closes-sidebar="true" />
						</Draggable>
					</Container>
				</div>
				<Container orientation="vertical"
					:drag-handle-selector="canEdit ? '.swimlane-header__drag-handle' : '.no-drag'"
					@drop="onReorderLane">
					<Draggable v-for="lane in computedLanes"
						:key="lane.key">
						<Swimlane :lane="lane"
							:stacks="stacksByBoard"
							:board-id="board.id" />
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
import CheckIcon from 'vue-material-design-icons/Check.vue'
import Stack from './Stack.vue'
import GanttView from './GanttView.vue'
import Swimlane from './Swimlane.vue'
import { NcEmptyContent, NcModal, NcButton, NcTextField, NcLoadingIcon } from '@nextcloud/vue'
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
		GanttView,
		Swimlane,
		NcEmptyContent,
		NcModal,
		NcTextField,
		NcButton,
		NcLoadingIcon,
		CheckIcon,
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
			'viewMode',
		]),
		stacksByBoard() {
			return this.board?.id ? this.$store.getters.stacksByBoard(this.board.id) : []
		},
		dragHandleSelector() {
			return this.canEdit ? '.stack__title' : '.no-drag'
		},
		isEmpty() {
			return this.stacksByBoard.length === 0
		},
		swimlaneMode() {
			return this.board?.settings?.swimlaneMode || 'none'
		},
		computedLanes() {
			if (this.swimlaneMode === 'none' || !this.board) {
				return []
			}
			const lanes = []
			const seen = new Set()
			let hasNoValue = false

			for (const stack of this.stacksByBoard) {
				const cards = this.$store.getters.cardsByStack(stack.id).filter(
					c => this.showArchived ? c.archived : !c.archived,
				)
				for (const card of cards) {
					if (this.swimlaneMode === 'labels') {
						if (!card.labels || card.labels.length === 0) {
							hasNoValue = true
						} else {
							for (const label of card.labels) {
								if (!seen.has(label.id)) {
									seen.add(label.id)
									lanes.push({
										key: 'label-' + label.id,
										id: label.id,
										type: 'label',
										title: label.title,
										color: label.color,
									})
								}
							}
						}
					} else if (this.swimlaneMode === 'assignees') {
						if (!card.assignedUsers || card.assignedUsers.length === 0) {
							hasNoValue = true
						} else {
							for (const user of card.assignedUsers) {
								// Skip entries whose participant was deleted or
								// couldn't be resolved (e.g. broken federation)
								// so the whole view doesn't unmount on bad data
								const uid = user?.participant?.uid
								if (!uid) continue
								if (!seen.has(uid)) {
									seen.add(uid)
									lanes.push({
										key: 'assignee-' + uid,
										id: uid,
										type: 'assignee',
										uid,
										title: user.participant.displayname,
									})
								}
							}
						}
					}
				}
			}

			const savedOrder = this.swimlaneMode === 'labels'
				? this.$store.state.swimlaneLabelOrder[this.board.id]
				: this.$store.state.swimlaneUserOrder[this.board.id]

			// Always sort to keep lane order stable across card moves.
			// Lanes in savedOrder come first in their saved order; the rest
			// fall back to a deterministic by-id ordering instead of card-
			// iteration order (which shuffles when cards are moved).
			lanes.sort((a, b) => {
				if (savedOrder && savedOrder.length > 0) {
					const aIdx = savedOrder.indexOf(a.id)
					const bIdx = savedOrder.indexOf(b.id)
					const aInSaved = aIdx !== -1
					const bInSaved = bIdx !== -1
					if (aInSaved && bInSaved) return aIdx - bIdx
					if (aInSaved) return -1
					if (bInSaved) return 1
				}
				if (typeof a.id === 'number' && typeof b.id === 'number') {
					return a.id - b.id
				}
				return String(a.id).localeCompare(String(b.id))
			})

			if (hasNoValue) {
				const noValueTitle = this.swimlaneMode === 'labels'
					? t('deck', 'No label')
					: t('deck', 'Unassigned')
				lanes.push({
					key: '__none__',
					id: '__none__',
					type: this.swimlaneMode === 'labels' ? 'label' : 'assignee',
					title: noValueTitle,
					color: null,
				})
			}

			return lanes
		},
	},
	watch: {
		id(newValue, oldValue) {
			this.fetchData()
		},
		showArchived() {
			this.fetchData()
		},
		isEmpty(newValue) {
			newValue && this.$nextTick(() => {
				this.$refs?.newStackInput?.focus()
			})
		},
	},
	created() {
		// Session is created in fetchData() after loadBoardById succeeds
		this.fetchData()
		this.$root.$on('open-card', (cardId) => {
			this.localModal = cardId
		})
	},
	beforeDestroy() {
		this.session?.close()
	},
	methods: {
		async fetchData() {
			this.loading = true
			try {
				await this.$store.dispatch('loadBoardById', this.id)
				await this.$store.dispatch('loadStacks', this.id)

				const routeCardId = this.$route?.params?.cardId ? parseInt(this.$route.params.cardId) : null
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

		onReorderLane({ removedIndex, addedIndex }) {
			if (removedIndex === null || addedIndex === null || !this.canEdit) {
				return
			}
			const lanes = [...this.computedLanes]
			const [moved] = lanes.splice(removedIndex, 1)
			lanes.splice(addedIndex, 0, moved)
			const order = lanes.map(l => l.id)
			const type = this.swimlaneMode === 'labels' ? 'labels' : 'assignees'
			this.$store.dispatch('setSwimlaneOrder', {
				boardId: this.board.id,
				type,
				order,
			})
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
	@import '../../css/animations.scss';
	@import '../../css/variables.scss';

	form {
		text-align: center;
		display: flex;
		width: 100%;
		margin: auto;
		margin-top: calc(var(--default-grid-baseline) * 4);
		gap: var(--default-grid-baseline);

		input[type="text"] {
			flex-grow: 1;
		}
		button[type="submit"] {
			flex-shrink: 0;
		}
	}

	.board-wrapper {
		position: relative;
		width: 100%;
		height: 100%;
		display: flex;
		flex-direction: column;
	}

	.board {
		position: relative;
		overflow-x: auto;
		flex-grow: 1;
		scrollbar-gutter: stable;

		&--swimlanes {
			overflow-y: auto;
			overflow-x: auto;
			padding: 0;
		}

		&__stack-headers {
			position: sticky;
			top: 0;
			z-index: 150;
			background-color: var(--color-main-background);

			.smooth-dnd-container.horizontal {
				height: auto;
			}
		}
	}

	/**
	 * Combined rules to handle proper board scrolling and
	 * drag and drop behavior
	 */
	.smooth-dnd-container.horizontal {
		display: flex;
		align-items: stretch;
		gap: $board-gap;
		padding: 0 $board-gap;
		height: 100%;

		&:deep(.stack-draggable-wrapper.smooth-dnd-draggable-wrapper) {
			display: flex;
			height: auto;
			flex: 0 1 $card-max-width;
			min-width: $card-min-width;

			.stack {
				display: flex;
				flex-direction: column;
				position: relative;

				.smooth-dnd-container.vertical {
					$margin-x: calc($stack-gap * -1);
					display: flex;
					flex-direction: column;
					gap: $stack-gap;
					padding: $stack-gap;
					margin: 0 $margin-x;
					overflow-y: auto;
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
