<!--
  - SPDX-FileCopyrightText: 2018 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<div>
		<Controls />
		<div class="board-list">
			<div class="board-list-row board-list-header-row">
				<div class="board-list-bullet-cell">
					<div class="board-list-bullet" />
				</div>
				<div class="board-list-title-cell">
					{{ t('deck', 'Board name') }}
				</div>
				<div class="board-list-avatars-cell">
					{{ t('deck', 'Members') }}
				</div>
				<div class="board-list-actions-cell" />
			</div>
			<BoardItem v-for="board in boardsSorted" :key="board.id" :board="board" />
		</div>
	</div>
</template>

<script>

import BoardItem from './BoardItem.vue'
import Controls from '../Controls.vue'

export default {
	name: 'Boards',
	components: {
		BoardItem,
		Controls,
	},
	props: {
		navFilter: {
			type: String,
			default: '',
		},
	},
	computed: {
		boardsSorted() {
			return [...this.filteredBoards].sort((a, b) => (a.title < b.title) ? -1 : 1)
		},
		filteredBoards() {
			const query = this.$store.getters.getSearchQuery
			return this.$store.getters.filteredBoards.filter((board) => {
				return board.deletedAt <= 0 && board.title.toLowerCase().includes(query.toLowerCase())
			})
		},
	},
	watch: {
		navFilter(value) {
			this.$store.commit('setBoardFilter', value)
		},
	},
}
</script>

<style lang="scss">
	.board-list {
		margin-top: - var(--default-clickable-area); //@TODO

		.board-list-row {
			align-items: center;
			border-bottom: 1px solid var(--color-border);
			display: flex;
		}

		.board-list-row:not(.board-list-header-row):hover {
			transition: background-color 0.3s ease;
			background-color: var(--color-background-dark);
		}

		.board-list-header-row {
			color: var(--color-text-lighter);
			height: var(--default-clickable-area);
		}

		.board-list-bullet-cell,
		.board-list-avatars-cell {
			padding: 6px 15px;
		}

		.board-list-avatars-cell {
			flex: 0 0 50px;
		}

		.board-list-avatar,
		.board-list-bullet {
			height: 32px;
			width: 32px;
		}

		.board-list-title-cell {
			flex: 1 0 auto;
			padding: 15px;
		}

		.board-list-actions-cell {
			// placeholder
			flex: 0 0 50px;
		}
	}
</style>
