<!--
  - @copyright Copyright (c) 2019 Julius Härtl <jus@bitgrid.net>
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
	<NcModal @close="close">
		<div id="modal-inner" :class="{ 'icon-loading': loading }">
			<h1>{{ t('deck', 'Select the board to link to a project') }}</h1>
			<input v-model="filter" type="text" :placeholder="t('deck', 'Search by board title')">
			<ul v-if="!loading">
				<li v-for="board in availableBoards"
					:key="board.id"
					:class="{'selected': (selectedBoard === board.id) }"
					@click="selectedBoard=board.id">
					<span :style="{ 'backgroundColor': '#' + board.color }" class="board-bullet" />
					<span>{{ board.title }}</span>
				</li>
			</ul>
			<button v-if="!loading" class="primary" @click="select">
				{{ t('deck', 'Select board') }}
			</button>
		</div>
	</NcModal>
</template>
<script>
import NcModal from '@nextcloud/vue/dist/Components/NcModal'
import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'

export default {
	name: 'BoardSelector',
	components: {
		NcModal,
	},
	data() {
		return {
			filter: '',
			boards: [],
			selectedBoard: null,
			loading: true,
			currentBoard: null,
		}
	},
	computed: {
		availableBoards() {
			return this.boards.filter((board) => (
				'' + board.id !== '' + this.currentBoard
				&& board.title.match(this.filter)
			))
		},
	},
	beforeMount() {
		this.fetchBoards()
		const hash = window.location.hash.match(/\/boards\/([0-9]+)/)
		this.currentBoard = hash.length > 0 ? hash[1] : null
	},
	methods: {
		fetchBoards() {
			axios.get(generateUrl('/apps/deck/boards')).then((response) => {
				this.boards = response.data
				this.loading = false
			})
		},
		close() {
			this.$root.$emit('close')
		},
		select() {
			this.$root.$emit('select', this.selectedBoard)
		},
	},

}
</script>
<style scoped>
	#modal-inner {
		width: 90vw;
		max-width: 400px;
		padding: 20px;
	}

	ul {
		min-height: 100px;
	}

	li {
		padding: 6px;
		border: 1px solid transparent;
	}

	li:hover, li:focus {
		background-color: var(--color-background-dark);
	}

	li.selected {
		border: 1px solid var(--color-primary);
	}

	.board-bullet {
		display: inline-block;
		width: 12px;
		height: 12px;
		border: none;
		border-radius: 50%;
		cursor: pointer;
	}

	li > span,
	.avatar {
		vertical-align: middle;

	}

</style>
