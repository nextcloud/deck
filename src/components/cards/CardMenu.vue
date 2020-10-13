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
	<div v-if="card">
		<div @click.stop.prevent>
			<Actions v-if="canEdit && !isArchived">
				<ActionButton v-if="showArchived === false && !isCurrentUserAssigned" icon="icon-user" @click="assignCardToMe()">
					{{ t('deck', 'Assign to me') }}
				</ActionButton>
				<ActionButton v-if="showArchived === false && isCurrentUserAssigned" icon="icon-user" @click="unassignCardFromMe()">
					{{ t('deck', 'Unassign myself') }}
				</ActionButton>
				<ActionButton icon="icon-external" @click.stop="modalShow=true">
					{{ t('deck', 'Move card') }}
				</ActionButton>
				<ActionButton icon="icon-settings-dark" @click="openCard">
					{{ t('deck', 'Card details') }}
				</ActionButton>
				<ActionButton icon="icon-archive" @click="archiveUnarchiveCard()">
					{{ showArchived ? t('deck', 'Unarchive card') : t('deck', 'Archive card') }}
				</ActionButton>
				<ActionButton v-if="showArchived === false" icon="icon-delete" @click="deleteCard()">
					{{ t('deck', 'Delete card') }}
				</ActionButton>
			</Actions>
		</div>
		<Modal v-if="modalShow" :title="t('deck', 'Move card to another board')" @close="modalShow=false">
			<div class="modal__content">
				<h3>{{ t('deck', 'Move card to another board') }}</h3>
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

				<button :disabled="!isBoardAndStackChoosen" class="primary" @click="moveCard">
					{{ t('deck', 'Move card') }}
				</button>
				<button @click="modalShow=false">
					{{ t('deck', 'Cancel') }}
				</button>
			</div>
		</Modal>
	</div>
</template>
<script>
import { Modal, Actions, ActionButton, Multiselect } from '@nextcloud/vue'
import { mapGetters, mapState } from 'vuex'
import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'
import { getCurrentUser } from '@nextcloud/auth'
import { showUndo } from '@nextcloud/dialogs'
import '@nextcloud/dialogs/styles/toast.scss'

export default {
	name: 'CardMenu',
	components: { Actions, ActionButton, Modal, Multiselect },
	props: {
		card: {
			type: Object,
			default: null,
		},
	},
	data() {
		return {
			modalShow: false,
			selectedBoard: '',
			selectedStack: '',
			stacksFromBoard: [],
		}
	},
	computed: {
		...mapGetters([
			'isArchived',
		]),
		...mapState({
			showArchived: state => state.showArchived,
			currentBoard: state => state.currentBoard,
		}),
		canEdit() {
			if (this.currentBoard) {
				return this.$store.getters.canEdit
			}
			const board = this.$store.getters.boards.find((item) => item.id === this.card.boardId)
			return !!board?.permissions?.PERMISSION_EDIT
		},
		isBoardAndStackChoosen() {
			if (this.selectedBoard === '' || this.selectedStack === '') {
				return false
			}
			return true
		},
		boards() {
			return this.$store.getters.boards.filter(board => {
				return board.id !== this.currentBoard.id
			})
		},
		isCurrentUserAssigned() {
			return this.card.assignedUsers.find((item) => item.type === 0 && item.participant.uid === getCurrentUser()?.uid)
		},
	},
	methods: {
		openCard() {
			this.$router.push({ name: 'card', params: { cardId: this.id } })
		},
		deleteCard() {
			this.$store.dispatch('deleteCard', this.card)
			showUndo(t('deck', 'Card deleted'), () => this.$store.dispatch('cardUndoDelete', this.card))
		},
		archiveUnarchiveCard() {
			this.$store.dispatch('archiveUnarchiveCard', { ...this.card, archived: !this.card.archived })
		},
		assignCardToMe() {
			this.$store.dispatch('assignCardToUser', {
				card: this.card,
				assignee: {
					userId: getCurrentUser()?.uid,
					type: 0,
				},
			})
		},
		unassignCardFromMe() {
			this.$store.dispatch('removeUserFromCard', {
				card: this.card,
				assignee: {
					userId: getCurrentUser()?.uid,
					type: 0,
				},
			})
		},
		moveCard() {
			this.copiedCard = Object.assign({}, this.card)
			this.copiedCard.stackId = this.selectedStack.id
			this.$store.dispatch('moveCard', this.copiedCard)
			this.modalShow = false
		},
		async loadStacksFromBoard(board) {
			try {
				const url = generateUrl('/apps/deck/stacks/' + board.id)
				const response = await axios.get(url)
				this.stacksFromBoard = response.data
			} catch (err) {
				return err
			}
		},
	},
}
</script>

<style lang="scss" scoped>
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
