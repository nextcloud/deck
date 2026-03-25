/*
 * SPDX-FileCopyrightText: 2018 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { mapGetters, mapState } from 'vuex'
import { generateUrl } from '@nextcloud/router'
import { getCurrentUser } from '@nextcloud/auth'
import { showUndo } from '../helpers/dialogs.js'
import { pushRoute } from '../router/navigation.js'
import { emit } from '@nextcloud/event-bus'

/**
 * Shared logic for card context-menu entries.
 * Used by CardMenu.vue (board cards) and CardSidebar.vue (sidebar actions)
 * so that NcActionButton items can live directly inside NcActions slots.
 */
export default {
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
		isInDoneColumn() {
			return this.stackById(this.card.stackId)?.isDoneColumn === true
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
		menuBoardId() {
			return this.card?.boardId ? this.card.boardId : Number(this.$route.params.id)
		},
		cardRichObject() {
			return {
				id: '' + this.card.id,
				name: this.card.title,
				boardname: this.boardById(this.menuBoardId)?.title,
				stackname: this.stackById(this.card.stackId)?.title,
				link: window.location.protocol + '//' + window.location.host + generateUrl('/apps/deck/') + `card/${this.card.id}`,
			}
		},
	},
	methods: {
		openCardFromEntries() {
			const boardId = this.card?.boardId ? this.card.boardId : this.$route?.params.id ?? this.currentBoard.id

			if (this.$router) {
				pushRoute(this.$router, { name: 'card', params: { id: boardId, cardId: this.card.id } })
				return
			}

			this.$emit('open-card', this.card.id)
		},
		editTitleFromEntries() {
			this.$emit('edit-title', this.card.id)
		},
		deleteCard() {
			this.$store.dispatch('deleteCard', this.card)
			const undoCard = { ...this.card, deletedAt: 0 }
			showUndo(t('deck', 'Card deleted'), () => this.$store.dispatch('cardUndoDelete', undoCard))
			if (this.$route?.name === 'card') {
				pushRoute(this.$router, { name: 'board' })
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
