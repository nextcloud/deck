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
	<div class="badges">
		<div v-if="card.commentsUnread > 0" class="icon icon-comment" />

		<div v-if="card.description && checkListCount > 0" class="card-tasks icon icon-checkmark">
			{{ checkListCheckedCount }}/{{ checkListCount }}
		</div>

		<div v-if="card.attachmentCount > 0" class="icon-attach icon icon-attach-dark">
			{{ card.attachmentCount }}
		</div>

		<AvatarList :users="card.assignedUsers" />

		<div @click.stop.prevent>
			<Actions v-if="canEdit">
				<ActionButton v-if="showArchived === false" icon="icon-user" @click="assignCardToMe()">
					{{ t('deck', 'Assign to me') }}
				</ActionButton>
				<ActionButton icon="icon-archive" @click="archiveUnarchiveCard()">
					{{ t('deck', (showArchived ? 'Unarchive card' : 'Archive card')) }}
				</ActionButton>
				<ActionButton v-if="showArchived === false" icon="icon-delete" @click="deleteCard()">
					{{ t('deck', 'Delete card') }}
				</ActionButton>
				<ActionButton icon="icon-external" @click.stop="modalShow=true">
					{{ t('deck', 'Move card') }}
				</ActionButton>
				<ActionButton icon="icon-settings-dark" @click="openCard">
					{{ t('deck', 'Card details') }}
				</ActionButton>
			</Actions>
		</div>
		<Modal v-if="modalShow" title="Move card to another board" @close="modalShow=false">
			<div class="modal__content">
				<Multiselect v-model="selectedBoard"
					:placeholder="t('deck', 'Select a board')"
					:options="boards"
					label="title"
					@select="loadStacksFromBoard" />
				<Multiselect v-model="selectedStack"
					:placeholder="t('deck', 'Select a stack')"
					:options="stacksFromBoard"
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
import AvatarList from './AvatarList'
import { Modal, Actions, ActionButton, Multiselect } from '@nextcloud/vue'
import { mapGetters, mapState } from 'vuex'
import axios from '@nextcloud/axios'

export default {
	name: 'CardBadges',
	components: { AvatarList, Actions, ActionButton, Modal, Multiselect },
	props: {
		id: {
			type: Number,
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
			'canEdit',
		]),
		...mapState({
			showArchived: state => state.showArchived,
			currentBoard: state => state.currentBoard,
		}),
		checkListCount() {
			return (this.card.description.match(/^\s*(\*|-|(\d\.))\s+\[\s*(\s|x)\s*\](.*)$/gim) || []).length
		},
		checkListCheckedCount() {
			return (this.card.description.match(/^\s*(\*|-|(\d\.))\s+\[\s*x\s*\](.*)$/gim) || []).length
		},
		compactMode() {
			return false
		},
		card() {
			return this.$store.getters.cardById(this.id)
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
	},
	methods: {
		openCard() {
			this.$router.push({ name: 'card', params: { cardId: this.id } })
		},
		deleteCard() {
			this.$store.dispatch('deleteCard', this.card)
		},
		archiveUnarchiveCard() {
			this.$store.dispatch('archiveUnarchiveCard', { ...this.card, archived: !this.card.archived })
		},
		assignCardToMe() {
			this.copiedCard = Object.assign({}, this.card)
			this.$store.dispatch('assignCardToUser', {
				card: this.copiedCard,
				assignee: {
					userId: OC.getCurrentUser().uid,
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
				console.debug(board)
				const url = OC.generateUrl('/apps/deck/stacks/' + board.id)
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
	.badges {
		display: flex;
		width: 100%;
		flex-grow: 1;

		.icon {
			opacity: 0.5;
			padding: 12px 18px;
			padding-right: 4px;
			margin-right: 5px;
			background-position: left;
			background-size: 16px;
			span {
				margin-left: 18px;
			}
			&.icon-edit {
				opacity: 0.5;
			}
		}
	}

	.badges .icon.due {
		background-position: 4px center;
		border-radius: 3px;
		margin-top: 10px;
		margin-bottom: 10px;
		padding: 4px;
		font-size: 90%;
		display: flex;
		align-items: center;
		opacity: .5;
		flex-shrink: 1;

		.icon {
			background-size: contain;
		}

		&.overdue {
			background-color: var(--color-error);
			color: var(--color-primary-text);
			opacity: .7;
		}
		&.now {
			background-color: var(--color-warning);
			opacity: .7;
		}
		&.next {
			background-color: var(--color-background-dark);
			opacity: .7;
		}

		span {
			margin-left: 20px;
			white-space: nowrap;
			text-overflow: ellipsis;
			overflow: hidden;
		}
	}

	.fade-enter-active, .fade-leave-active {
		transition: opacity .125s;
	}
	.fade-enter, .fade-leave-to {
		opacity: 0;
	}

	.modal__content {
		width: 25vw;
		min-width: 250px;
		height: 120px;
		text-align: center;
		margin: 20px 20px 60px 20px;

		.multiselect {
			margin-bottom: 10px;
		}
	}

	.modal__content button {
		float: right;
	}
</style>
