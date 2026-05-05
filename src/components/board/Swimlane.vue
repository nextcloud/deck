<!--
  - SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<section class="swimlane" :aria-label="lane.title">
		<SwimlaneHeader :lane="lane"
			:card-count="cardCount"
			:collapsed="collapsed"
			@toggle="toggleCollapse" />

		<div v-show="!collapsed" class="swimlane__stacks">
			<Container lock-axis="y"
				orientation="horizontal"
				:drag-handle-selector="dragHandleSelector"
				data-click-closes-sidebar="true"
				@drag-start="draggingStack = true"
				@drag-end="draggingStack = false"
				@drop="onDropStack">
				<Draggable v-for="stack in stacks"
					:key="stack.id"
					data-click-closes-sidebar="true"
					data-dragscroll-enabled
					class="stack-draggable-wrapper">
					<Stack :stack="stack"
						:lane="lane"
						:dragging="draggingStack"
						data-click-closes-sidebar="true" />
				</Draggable>
			</Container>
		</div>
	</section>
</template>

<script>
import { Container, Draggable } from 'vue-smooth-dnd'
import Stack from './Stack.vue'
import SwimlaneHeader from './SwimlaneHeader.vue'
import { mapGetters } from 'vuex'

export default {
	name: 'Swimlane',
	components: {
		Container,
		Draggable,
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
		let initialCollapsed = false
		const stored = localStorage.getItem(`deck.board.${this.boardId}.swimlane.collapsed`)
		if (stored) {
			try {
				initialCollapsed = !!JSON.parse(stored)[this.lane.key]
			} catch (e) {
				// ignore
			}
		}
		return {
			draggingStack: false,
			collapsed: initialCollapsed,
		}
	},
	computed: {
		...mapGetters(['canEdit']),
		cardCount() {
			let count = 0
			for (const stack of this.stacks) {
				count += this.$store.getters.cardsByStackAndLane(stack.id, this.lane.type, this.lane.id).length
			}
			return count
		},
		dragHandleSelector() {
			return this.canEdit ? '.stack__title' : '.no-drag'
		},
	},
	methods: {
		toggleCollapse() {
			this.collapsed = !this.collapsed
			const storageKey = `deck.board.${this.boardId}.swimlane.collapsed`
			let stored = {}
			try {
				stored = JSON.parse(localStorage.getItem(storageKey)) || {}
			} catch (e) {
				stored = {}
			}
			stored[this.lane.key] = this.collapsed
			localStorage.setItem(storageKey, JSON.stringify(stored))
		},
		onDropStack({ removedIndex, addedIndex }) {
			this.$store.dispatch('orderStack', {
				stack: this.stacks[removedIndex],
				removedIndex,
				addedIndex,
			})
		},
	},
}
</script>

<style lang="scss" scoped>
@import '../../css/variables';

.swimlane {
	margin-bottom: calc(var(--default-grid-baseline) * 3);

	&__stacks {
		overflow-x: auto;
		padding-left: $board-gap;

		:deep(.smooth-dnd-container.horizontal) {
			display: flex;
			align-items: stretch;

			.stack-draggable-wrapper.smooth-dnd-draggable-wrapper {
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
						margin-left: $stack-gap;
						padding-right: $stack-gap;
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
	}
}
</style>
