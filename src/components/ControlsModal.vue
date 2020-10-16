<!--
* @copyright Copyright (c) 2020 Jakob Röhrl <jakob.roehrl@web.de>
*
* @author Jakob Röhrl <jakob.roehrl@web.de>
*
* @license GNU AGPL version 3 or any later version
*
* This program is free software: you can redistribute it and/or modify
* it under the terms of the GNU Affero General Public License as
* published by the Free Software Foundation, either version 3 of the
* License, or (at your option) any later version.
*
* This program is distributed in the hope that it will be useful,
* but WITHOUT ANY WARRANTY; without even the implied warranty of
* MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
* GNU Affero General Public License for more details.
*
* You should have received a copy of the GNU Affero General Public License
* along with this program. If not, see <http://www.gnu.org/licenses/>.
*
-->

<template>
	<Modal v-if="modalShow" :title="t('deck', 'Add card on Today')" @close="modalShow=false">
		<div class="modal__content">
			{{ lastBoardId - selectedBoard }}
			{{ lastListId }}
			<h3>{{ t('deck', 'Add card on Today') }}</h3>
			<Multiselect v-model="selectedBoard"
				:placeholder="t('deck', 'Select a board')"
				:options="boards"
				:max-height="100"
				label="title"
				@select="loadStacksFromBoard" />
			<Multiselect v-model="selectedStack"
				:placeholder="t('deck', 'Select a list')"
				:options="stacksFromBoard"
				:max-height="100"
				label="title" />

			<label for="new-stack-input-main" class="hidden-visually">{{ t('deck', 'Add card on Today') }}</label>
			<input id="new-stack-input-main"
				ref="newCardInput"
				v-model="newCardTitle"
				v-focus
				type="text"
				class="no-close"
				:placeholder="t('deck', 'Card name')">

			<button :disabled="!addCardSetRequiredFields" class="primary" @click="addCard">
				{{ t('deck', 'Add card') }}
			</button>
			<button @click="modalShow=false">
				{{ t('deck', 'Cancel') }}
			</button>
		</div>
	</Modal>
</template>

<script>
import { Modal, Multiselect } from '@nextcloud/vue'
import labelStyle from '../mixins/labelStyle'
import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'
import { showError } from '@nextcloud/dialogs'

export default {
	name: 'ControlsModal',
	components: {
		Modal, Multiselect,
	},
	mixins: [ labelStyle ],
	props: {
		modalShow: {
			default: false,
			type: Boolean,
		},

	},
	data() {
		return {
			selectedBoard: '',
			selectedStack: '',
			stacksFromBoard: [],
			newCardTitle: '',
			lastBoardId: localStorage.getItem('deck.lastBoardId'),
			lastListId: localStorage.getItem('deck.lastListId'),
		}
	},
	computed: {
		boards() {
			return this.$store.getters.boards
		},
		addCardSetRequiredFields() {
			if (this.selectedBoard === '' || this.selectedStack === '' || this.newCardTitle.trim() === '') {
				return false
			}
			return true
		},
	},
	mounted() {
		this.setLastBoardId()
	},

	methods: {
		setLastBoardId() {
			if (this.lastBoardId === null || this.lastBoardId === 0) {
				this.selectedBoard = ''
				return
			}
			this.selectedBoard = this.selectedBoard = this.boards.filter(board => {
				return board.id === this.lastBoardId
			})
		},
		async loadStacksFromBoard(selectedBoard) {
			try {
				const url = generateUrl('/apps/deck/stacks/' + selectedBoard.id)
				const response = await axios.get(url)
				this.stacksFromBoard = response.data
			} catch (err) {
				return err
			}
		},
		async addCard() {
			try {
				const today = new Date()
				today.setHours(23, 59, 59, 999)
				await this.$store.dispatch('addCard', {
					title: this.newCardTitle,
					stackId: this.selectedStack.id,
					boardId: this.selectedBoard.id,
					duedate: today.toISOString(),
				})
				this.newCardTitle = ''
				localStorage.setItem('deck.lastBoardId', this.selectedBoard.id)
				localStorage.setItem('deck.lastListId', this.selectedStack.id)

			} catch (e) {
				showError('Could not create card: ' + e.response.data.message)
			}
			// this.modalShow = false
		},
	},
}
</script>

<style lang="scss" scoped>
	#new-stack-input-main {
		width: 100%;
	}

	.modal__content {
		width: 25vw;
		min-width: 250px;
		min-height: 120px;
		text-align: center;
		margin: 20px 20px 100px 20px;

		.multiselect {
			margin-bottom: 10px;
		}
	}

	.modal__content button {
		float: right;
		margin-top: 50px;
	}
</style>
