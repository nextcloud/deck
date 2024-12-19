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
	<div>
		<NcActionButton v-if="!hideDetailsEntry" :close-after-click="true" @click="openCard">
			<CardBulletedIcon slot="icon" :size="20" decorative />
			{{ t('deck', 'Card details') }}
		</NcActionButton>
		<NcActionButton v-if="canEdit && !isCurrentUserAssigned"
			icon="icon-user"
			:close-after-click="true"
			@click="assignCardToMe()">
			{{ t('deck', 'Assign to me') }}
		</NcActionButton>
		<NcActionButton v-if="canEdit && isCurrentUserAssigned"
			icon="icon-user"
			:close-after-click="true"
			@click="unassignCardFromMe()">
			{{ t('deck', 'Unassign myself') }}
		</NcActionButton>
		<NcActionButton v-if="canEdit"
			icon="icon-checkmark"
			:close-after-click="true"
			@click="changeCardDoneStatus()">
			{{ card.done ? t('deck', 'Mark as not done') : t('deck', 'Mark as done') }}
		</NcActionButton>
		<NcActionButton v-if="canEdit"
			icon="icon-external"
			:close-after-click="true"
			@click="openCardMoveDialog">
			{{ t('deck', 'Move card') }}
		</NcActionButton>
		<NcActionButton v-for="action in cardActions"
			:key="action.label"
			:close-after-click="true"
			:icon="action.icon"
			@click="action.callback(cardRichObject)">
			{{ action.label }}
		</NcActionButton>
		<NcActionButton v-if="canEditBoard" :close-after-click="true" @click="archiveUnarchiveCard()">
			<template #icon>
				<ArchiveIcon :size="20" decorative />
			</template>
			{{ card.archived ? t('deck', 'Unarchive card') : t('deck', 'Archive card') }}
		</NcActionButton>
		<NcActionButton v-if="canEdit"
			icon="icon-delete"
			:close-after-click="true"
			@click="deleteCard()">
			{{ t('deck', 'Delete card') }}
		</NcActionButton>
	</div>
</template>
<script>
import { NcActionButton } from '@nextcloud/vue'
import { mapGetters, mapState } from 'vuex'
import ArchiveIcon from 'vue-material-design-icons/Archive.vue'
import CardBulletedIcon from 'vue-material-design-icons/CardBulleted.vue'
import { generateUrl } from '@nextcloud/router'
import { getCurrentUser } from '@nextcloud/auth'
import { showUndo } from '@nextcloud/dialogs'

import '@nextcloud/dialogs/style.css'
import { emit } from '@nextcloud/event-bus'

export default {
	name: 'CardMenuEntries',
	components: { NcActionButton, ArchiveIcon, CardBulletedIcon },
	props: {
		card: {
			type: Object,
			default: null,
		},
		hideDetailsEntry: {
			type: Boolean,
			default: false,
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
			'boards',
			'cardActions',
			'stackById',
			'boardById',
		]),
		...mapState({
			showArchived: state => state.showArchived,
			currentBoard: state => state.currentBoard,
		}),
		canEdit() {
			return !this.card.archived
		},
		canEditBoard() {
			if (this.currentBoard) {
				return this.$store.getters.canEdit
			}
			const board = this.$store.getters.boards.find((item) => item.id === this.card.boardId)
			return !!board?.permissions?.PERMISSION_EDIT
		},
		isCurrentUserAssigned() {
			return this.card.assignedUsers.find((item) => item.type === 0 && item.participant.uid === getCurrentUser()?.uid)
		},
		boardId() {
			return this.card?.boardId ? this.card.boardId : Number(this.$route.params.id)
		},
		cardRichObject() {
			return {
				id: '' + this.card.id,
				name: this.card.title,
				boardname: this.boardById(this.boardId)?.title,
				stackname: this.stackById(this.card.stackId)?.title,
				link: window.location.protocol + '//' + window.location.host + generateUrl('/apps/deck/') + `card/${this.card.id}`,
			}
		},
	},
	methods: {
		openCard() {
			const boardId = this.card?.boardId ? this.card.boardId : this.$route.params.id
			this.$router.push({ name: 'card', params: { id: boardId, cardId: this.card.id } }).catch(() => {})
		},
		deleteCard() {
			this.$store.dispatch('deleteCard', this.card)
			const undoCard = { ...this.card, deletedAt: 0 }
			showUndo(t('deck', 'Card deleted'), () => this.$store.dispatch('cardUndoDelete', undoCard))
			if (this.$router.currentRoute.name === 'card') {
				this.$router.push({ name: 'board' })
			}
		},
		changeCardDoneStatus() {
			this.$store.dispatch('changeCardDoneStatus', { ...this.card, done: !this.card.done })
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
		openCardMoveDialog() {
			emit('deck:card:show-move-dialog', this.card)
		},
	},
}
</script>
