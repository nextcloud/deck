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
	<div class="board-list">
		<div class="board-list-row board-list-header-row">
			<div class="board-list-bullet-cell"><div class="board-list-bullet" /></div>
			<div class="board-list-title-cell">Title</div>
			<div class="board-list-avatars-cell">Members</div>
			<div class="board-list-actions-cell" />
		</div>
		<BoardItem v-for="board in filteredBoards" :key="board.id" :board="board" />
	</div>
</template>

<script>

import BoardItem from './boards/BoardItem'
import { mapGetters } from 'vuex'

export default {
	name: 'Main',
	components: {
		BoardItem
	},
	props: {
		navFilter: {
			type: String,
			default: ''
		}
	},
	computed: {
		...mapGetters('boards', [
			'filteredBoards'
		])
	},
	watch: {
		navFilter: function(value) {
			this.$store.commit('boards/setFilter', value)
		}
	}
}
</script>

<style lang="scss">
	.board-list {

		.board-list-row {
			align-items: center;
			border-bottom: 1px solid #ededed;
			display: flex;
		}

		.board-list-header-row {
			color: var(--color-text-lighter);
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
