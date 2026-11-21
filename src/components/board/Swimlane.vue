<!--
  - SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<section class="swimlane" :aria-label="lane.title">
		<SwimlaneHeader
			:lane="lane"
			:cardCount="cardCount"
			:collapsed="collapsed"
			@toggle="toggleCollapse" />

		<!-- v-if, not v-show: a collapsed lane mounts no drag containers. -->
		<div v-if="!collapsed" class="swimlane__stacks">
			<div
				v-for="stack in stacks"
				:key="stack.id"
				class="swimlane__stack"
				data-click-closes-sidebar="true"
				data-dragscroll-enabled>
				<Stack
					:stack="stack"
					:lane="lane"
					part="cards"
					data-click-closes-sidebar="true" />
			</div>
		</div>
	</section>
</template>

<script>
import Stack from './Stack.vue'
import SwimlaneHeader from './SwimlaneHeader.vue'
import { useCardStore } from '../../stores/card.js'

// Per-user, per-board collapse state lives in localStorage under this key.
const collapseStorageKey = (boardId) => `deck.board.${boardId}.swimlane.collapsed`

// Missing, broken or non-object values count as "nothing collapsed".
function readCollapsed(boardId) {
	try {
		const stored = JSON.parse(localStorage.getItem(collapseStorageKey(boardId)))
		return stored && typeof stored === 'object' ? stored : {}
	} catch {
		return {}
	}
}

export default {
	name: 'Swimlane',
	components: {
		Stack,
		SwimlaneHeader,
	},

	props: {
		lane: {
			type: Object,
			required: true,
		},

		stacks: {
			type: Array,
			required: true,
		},

		boardId: {
			type: Number,
			required: true,
		},
	},

	data() {
		return {
			collapsed: !!readCollapsed(this.boardId)[this.lane.type + '-' + this.lane.key],
		}
	},

	computed: {
		cardCount() {
			const map = useCardStore(this.$pinia).cardsByStackAndLaneMap
			const key = this.lane.key
			let count = 0
			for (const stack of this.stacks) {
				const lanes = map[stack.id]
				if (lanes && lanes[key]) {
					count += lanes[key].length
				}
			}
			return count
		},
	},

	methods: {
		toggleCollapse() {
			this.collapsed = !this.collapsed
			const stored = readCollapsed(this.boardId)
			stored[this.lane.type + '-' + this.lane.key] = this.collapsed
			localStorage.setItem(collapseStorageKey(this.boardId), JSON.stringify(stored))
		},
	},
}
</script>

<style lang="scss" scoped>
@use '../../css/variables.scss' as *;

.swimlane {
	margin-bottom: calc(var(--default-grid-baseline) * 3);

	&__stacks {
		// .board--swimlanes scrolls horizontally, so lanes stay aligned with the header row.
		padding-inline: $board-gap;
		display: flex;
		align-items: stretch;
		gap: $board-gap;
	}

	&__stack {
		display: flex;
		height: auto;
		flex: 0 1 $card-max-width;
		min-width: $card-min-width;

		:deep(.stack) {
			display: flex;
			flex-direction: column;
			position: relative;

			// Same values as the stack rule in Board.vue, the lane stacks sit outside its selector.
			.smooth-dnd-container.vertical {
				display: flex;
				flex-direction: column;
				gap: $stack-gap;
				padding: $stack-gap;
				margin: 0 calc(#{$stack-gap} * -1);
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
