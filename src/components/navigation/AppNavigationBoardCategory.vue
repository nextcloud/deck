<!--
  - SPDX-FileCopyrightText: 2018 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<NcAppNavigationItem v-if="boards.length > 0"
		:name="text"
		:to="to"
		:exact="true"
		:allow-collapse="collapsible"
		:open="opened">
		<Container v-if="sortable"
			lock-axis="y"
			tag="div"
			drag-handle-selector=".board-drag-handle"
			@drop="onDropBoard">
			<Draggable v-for="board in boardsSorted" :key="board.id">
				<div class="board-drag-row">
					<span class="board-drag-handle" :title="t('deck', 'Drag to reorder')">
						<DragVertical :size="20" />
					</span>
					<AppNavigationBoard :board="board" />
				</div>
			</Draggable>
		</Container>
		<template v-else>
			<AppNavigationBoard v-for="board in boardsSorted" :key="board.id" :board="board" />
		</template>
		<template #icon>
			<slot name="icon" />
		</template>
	</NcAppNavigationItem>
</template>

<script>
import AppNavigationBoard from './AppNavigationBoard.vue'
import { NcAppNavigationItem } from '@nextcloud/vue'
import { Container, Draggable } from 'vue-smooth-dnd'
import { showError } from '@nextcloud/dialogs'
import DragVertical from 'vue-material-design-icons/DragVertical.vue'
import { sortBoards } from '../../helpers/boardSort.js'

export default {
	name: 'AppNavigationBoardCategory',
	components: {
		NcAppNavigationItem,
		AppNavigationBoard,
		Container,
		Draggable,
		DragVertical,
	},
	props: {
		to: {
			type: String,
			default: '',
		},
		id: {
			type: String,
			required: true,
		},
		text: {
			type: String,
			required: true,
		},
		boards: {
			type: Array,
			required: true,
		},
		/**
		 * Control whether the category should be opened when adding boards.
		 * This is for example used in the case a new board has been added, so the user directly sees it.
		 */
		openOnAddBoards: {
			type: Boolean,
			default: false,
		},
		defaultOpen: {
			type: Boolean,
			default: false,
		},
		sortable: {
			type: Boolean,
			default: false,
		},
	},
	data() {
		return {
			opened: false,
		}
	},
	computed: {
		boardsSorted() {
			return sortBoards(this.boards, this.sortable ? this.$store.getters.config('boardOrder') : null)
		},
		collapsible() {
			return this.boards.length > 0
		},
	},
	watch: {
		boards(newVal, prevVal) {
			if (this.openOnAddBoards === true && prevVal.length < newVal.length) {
				this.opened = true
			}
		},
	},
	mounted() {
		this.opened = this.defaultOpen
	},
	methods: {
		async onDropBoard({ removedIndex, addedIndex }) {
			if (removedIndex === null || addedIndex === null || removedIndex === addedIndex) {
				return
			}
			const ordered = [...this.boardsSorted]
			const [moved] = ordered.splice(removedIndex, 1)
			ordered.splice(addedIndex, 0, moved)
			try {
				await this.$store.dispatch('setConfig', { boardOrder: ordered.map((board) => board.id) })
			} catch (error) {
				console.error('Failed to reorder boards', error)
				showError(t('deck', 'Failed to reorder boards'))
			}
		},
	},
}
</script>

<style lang="scss" scoped>
.smooth-dnd-container {
	display: flex;
	flex-direction: column;
	gap: var(--default-grid-baseline, 4px);
}

.board-drag-row {
	display: flex;
	align-items: center;

	> :last-child {
		flex: 1 1 auto;
		min-width: 0;
	}
}

.board-drag-handle {
	display: flex;
	align-items: center;
	justify-content: center;
	flex: 0 0 24px;
	color: var(--color-text-maxcontrast);
	cursor: grab;
	opacity: 0;
	transition: opacity var(--animation-quick, 100ms) ease;
}

.board-drag-row:hover .board-drag-handle,
.board-drag-handle:focus-visible {
	opacity: 1;
}
</style>
