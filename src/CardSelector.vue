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
	<Modal :title="t('deck', 'Select the card to link to a project')" @close="close">
		<div id="modal-inner" :class="{ 'icon-loading': loading }">
			<Multiselect v-model="selectedBoard"
				:placeholder="t('deck', 'Select a board')"
				:options="boards"
				label="title"
				@select="fetchCardsFromBoard" />

			<Multiselect v-model="selectedCard"
				:placeholder="t('deck', 'Select a card')"
				:options="cardsFromBoard"
				label="title" />

			<button :disabled="!isBoardAndStackChoosen" class="primary" @click="select">
				{{ t('deck', 'Link to card') }}
			</button>
			<button @click="close">
				{{ t('deck', 'Cancel') }}
			</button>
		</div>
	</Modal>
</template>

<script>
import { Modal } from '@nextcloud/vue/dist/Components/Modal'
import { Multiselect } from '@nextcloud/vue/dist/Components/Multiselect'
import axios from 'nextcloud-axios'

export default {
	name: 'CardSelector',
	components: {
		Modal,
		Multiselect,
	},
	data() {
		return {
			boards: [],
			selectedBoard: '',
			cardsFromBoard: [],
			selectedCard: '',
			loading: true,
		}
	},
	computed: {
		isBoardAndStackChoosen() {
			if (this.selectedBoard === '' || this.selectedCard === '') {
				return false
			}
			return true
		},
	},
	beforeMount() {
		this.fetchBoards()
	},
	methods: {
		fetchBoards() {
			axios.get(OC.generateUrl('/apps/deck/boards')).then((response) => {
				this.boards = response.data
				this.loading = false
			})
		},
		async fetchCardsFromBoard(board) {
			try {
				this.cardsFromBoard = []
				const url = OC.generateUrl('/apps/deck/stacks/' + board.id)
				const response = await axios.get(url)
				response.data.forEach(stack => {
					this.cardsFromBoard.push(...stack.cards)
				})
			} catch (err) {
				return err
			}

		},
		close() {
			this.$root.$emit('close')
		},
		select() {
			this.$root.$emit('select', this.selectedCard.id)

		},
	},

}
</script>

<style scoped>
	#modal-inner {
		width: 90vw;
		max-width: 400px;
		padding: 20px;
		height: 500px;
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
	button {
		float: right;
	}
</style>
