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
			<h1>Select a board to add to the collection</h1>
			<ul v-if="!loading">
				<li v-for="board in boards" @click="selectedBoard=board.id" :class="{'selected': (selectedBoard === board.id) }">
					<span class="board-bullet" :style="{ 'backgroundColor': '#' + board.color }"></span>
					<span>{{ board.title }}</span>
				</li>
			</ul>
			<button v-if="!loading" @click="select" class="primary">Select board</button>
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
			}
		},
		beforeMount() {
			this.fetchBoards();
		},
		methods: {
			fetchBoards() {
				axios.get(OC.generateUrl('/apps/deck/boards')).then((response) => {
					this.boards = response.data
					this.loading = false
				})
			},
			close() {
				this.$root.$emit('close');
			},
			select() {
				this.$root.$emit('select', this.selectedBoard)

			}
		},
		computed: {

		},

	}
</script>
