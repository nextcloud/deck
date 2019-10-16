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
	<Modal @close="close">
		<div id="modal-inner" :class="{ 'icon-loading': loading }">
			<h1>{{ t('deck', 'Select the board to link to a project') }}</h1>
			<ul v-if="!loading">
				<li v-for="board in boards" v-if="!currentBoard || ''+board.id !== ''+currentBoard" :class="{'selected': (selectedBoard === board.id) }"
					@click="selectedBoard=board.id">
					<span :style="{ 'backgroundColor': '#' + board.color }" class="board-bullet" />
					<span>{{ board.title }}</span>
				</li>
			</ul>
			<button v-if="!loading" class="primary" @click="select">{{ t('deck', 'Select board') }}</button>
		</div>
	</Modal>
</template>
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
<script>
/* global OC */
import { Modal } from 'nextcloud-vue/dist/Components/Modal'
import { Avatar } from 'nextcloud-vue/dist/Components/Avatar'
import axios from 'nextcloud-axios'

export default {
	name: 'CollaborationView',
	components: {
		Modal, Avatar
	},
	data() {
		return {
			boards: [],
			selectedBoard: null,
			loading: true,
			currentBoard: null
		}
	},
	computed: {

	},
	beforeMount() {
		this.fetchBoards()
		if (typeof angular !== 'undefined' && angular.element('#board')) {
			try {
				this.currentBoard = angular.element('#board').scope().boardservice.id || null
			} catch (e) {}
		}
	},
	methods: {
		fetchBoards() {
			axios.get(OC.generateUrl('/apps/deck/boards')).then((response) => {
				this.boards = response.data
				this.loading = false
			})
		},
		close() {
			this.$root.$emit('close')
		},
		select() {
			this.$root.$emit('select', this.selectedBoard)

		}
	}

}
</script>
