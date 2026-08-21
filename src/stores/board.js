/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import Vue from 'vue'
import { defineStore } from 'pinia'
import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'
import { BoardApi } from '../services/BoardApi.js'
import { useStackStore } from './stack.js'
import { useCardStore } from './card.js'
import { loadState } from '@nextcloud/initial-state'

const apiClient = new BoardApi()

export const BOARD_FILTERS = {
	ALL: '',
	ARCHIVED: 'archived',
	SHARED: 'shared',
}

export const useBoardStore = defineStore('board', {
	state: () => ({
		currentBoard: null,
		showArchived: false,
		viewModeByBoard: {},
		assignableUsers: [],
		filter: { tags: [], users: [], due: '', unassigned: false, completed: 'both' },
		boardFilter: BOARD_FILTERS.ALL,
		boardViews: [],
		boards: loadState('deck', 'initialBoards', {}),
	}),
	getters: {
		boardById: (state) => (id) => {
			return state.boards.find((board) => board.id === id)
		},
		viewMode(state) {
			if (!state.currentBoard) return 'kanban'
			if (state.viewModeByBoard[state.currentBoard.id] !== undefined) {
				return state.viewModeByBoard[state.currentBoard.id]
			}

			const stored = localStorage.getItem(`deck.viewMode.${state.currentBoard.id}`)
			return stored !== null ? stored : 'kanban'
		},
		assignables: state => {
			if (!state.currentBoard) {
				return []
			}

			return [
				...state.assignableUsers.map((user) => ({ ...user, type: user.type })),
				...state.currentBoard.acl.filter((acl) => acl.type === 1 && typeof acl.participant === 'object').map((group) => ({ ...group.participant, type: 1 })),
				...state.currentBoard.acl.filter((acl) => acl.type === 7 && typeof acl.participant === 'object').map((circle) => ({ ...circle.participant, type: 7 })),
			]
		},
		noneArchivedBoards: state => {
			return state.boards.filter(board => {
				return board.archived === false && !board.deletedAt
			})
		},
		archivedBoards: state => {
			return state.boards.filter(board => {
				return board.archived === true && !board.deletedAt
			})
		},
		sharedBoards: state => {
			return state.boards.filter(board => {
				return board.shared && !board.deletedAt
			})
		},
		filteredBoards: state => {
			// filters the boards depending on the active filter
			const boards = state.boards.filter(board => {
				return (state.boardFilter === BOARD_FILTERS.ALL && board.archived === false)
					|| (state.boardFilter === BOARD_FILTERS.ARCHIVED && board.archived === true)
					|| (state.boardFilter === BOARD_FILTERS.SHARED && board.shared === 1)
			})
			return boards
		},
		currentBoardLabels: state => {
			return state.currentBoard ? state.currentBoard.labels : []
		},
		canEdit: state => {
			return state.currentBoard ? state.currentBoard.permissions.PERMISSION_EDIT : false
		},
		canManage: state => {
			return state.currentBoard ? state.currentBoard.permissions.PERMISSION_MANAGE : false
		},
		canShare: state => {
			return state.currentBoard ? state.currentBoard.permissions.PERMISSION_SHARE : false
		},
		isArchived: state => {
			return state.currentBoard && state.currentBoard.archived
		},
	},
	actions: {
		setBoards(boards) {
			this.boards = boards
		},
		setBoardFilter(filter) {
			this.boardFilter = filter
		},
		toggleFilter(filter) {
			Object.keys(filter).forEach((key) => {
				switch (key) {
				case 'due':
					this.filter.due = filter.due
					break
				default:
					filter[key].forEach((item) => {
						const index = this.filter[key].indexOf(item)
						if (index === -1) {
							this.filter[key].push(item)
						} else {
							this.filter[key].splice(index, 1)
						}
					})
					break
				}
			})
		},
		async loadBoards() {
			const boards = await apiClient.loadBoards()
			this.boards = boards
		},
		setCurrentBoard(board) {
			this.currentBoard = board
		},
		setAssignableUsers(users) {
			this.assignableUsers = users
		},
		setFilterInStore(filter) {
			Object.assign(this.filter, filter)
		},
		toggleShowArchived(newState = undefined) {
			this.showArchived = newState !== undefined ? newState : !this.showArchived
		},
		setViewMode(mode) {
			if (!this.currentBoard) return
			Vue.set(this.viewModeByBoard, this.currentBoard.id, mode)
			localStorage.setItem(`deck.viewMode.${this.currentBoard.id}`, mode)
		},
		async loadBoardById(boardId) {
			this.filter = { tags: [], users: [], due: '', unassigned: false, completed: 'both' }
			this.boardViews = []
			this.setCurrentBoard(null)
			const board = await apiClient.loadById(boardId)
			this.setCurrentBoard(board)
			this.setAssignableUsers(board.users)
			await this.loadBoardViews(boardId)
		},
		async loadBoardViews(boardId) {
			this.boardViews = await apiClient.loadBoardViews(boardId)
			return this.boardViews
		},
		async createBoardView(boardId, name) {
			const view = await apiClient.createBoardView(boardId, name, this.filter)
			this.boardViews.push(view)
			return view
		},
		async updateBoardView(view) {
			const updated = await apiClient.updateBoardView(view.boardId, view)
			const index = this.boardViews.findIndex((v) => v.id === updated.id)
			if (index > -1) {
				Vue.set(this.boardViews, index, updated)
			}
			return updated
		},
		async deleteBoardView(boardId, viewId) {
			await apiClient.deleteBoardView(boardId, viewId)
			this.boardViews = this.boardViews.filter((v) => v.id !== viewId)
		},
		applyBoardView(view) {
			this.setFilterInStore(this.normalizeFilter(view.filters))
		},
		normalizeFilter(filter) {
			const defaults = { tags: [], users: [], due: '', unassigned: false, completed: 'both' }
			return { ...defaults, ...(filter || {}) }
		},
		async refreshBoard(boardId) {
			const board = await apiClient.loadById(boardId)
			const etagHasChanged = board.ETag !== this.currentBoard?.ETag
			this.setCurrentBoard(board)
			this.setAssignableUsers(board.users)

			if (etagHasChanged) {
				useStackStore().loadStacks(boardId)
			}
		},

		async createBoard(boardData) {
			try {
				const board = await apiClient.createBoard(boardData)
				this.addBoard(board)
			} catch (err) {
				return err
			}
		},

		async importBoard(file) {
			try {
				const board = await apiClient.importBoard(file)
				this.addBoard(board)
			} catch (err) {
				return err
			}
		},

		async cloneBoard({ boardData, settings }) {
			const { withCards, withAssignments, withLabels, withDueDate, moveCardsToLeftStack, restoreArchivedCards } = settings
			try {
				const newBoard = await apiClient.cloneBoard(boardData, withCards, withAssignments, withLabels, withDueDate, moveCardsToLeftStack, restoreArchivedCards)
				const indexExisting = this.boards.findIndex((b) => {
					return newBoard.id === b.id
				})

				if (indexExisting > -1) {
					Vue.set(this.boards, indexExisting, newBoard)
				} else {
					this.boards.push(newBoard)
				}
				return newBoard
			} catch (err) {
				return err
			}
		},

		archiveBoard(board) {
			const boardCopy = JSON.parse(JSON.stringify(board))
			boardCopy.archived = true
			apiClient.updateBoard(boardCopy)
				.then((board) => {
					this.addBoard(board)
				})
		},
		unarchiveBoard(board) {
			const boardCopy = JSON.parse(JSON.stringify(board))
			boardCopy.archived = false
			apiClient.updateBoard(boardCopy)
				.then((board) => {
					this.addBoard(board)
				})
		},

		/*
		 * Adds or replaces a board in the store.
		 * Matches a board by it's id.
		 */
		addBoard(board) {
			const indexExisting = this.boards.findIndex((b) => {
				return board.id === b.id
			})

			if (indexExisting > -1) {
				Vue.set(this.boards, indexExisting, board)
			} else {
				this.boards.push(board)
			}
		},
		removeBoard(board) {
			this.boards = this.boards.filter((b) => {
				return b.id !== board.id
			})
		},
		async updateBoard(board) {
			const storedBoard = await apiClient.updateBoard(board)
			this.addBoard(storedBoard)

			if (this.currentBoard?.id === storedBoard.id) {
				this.setCurrentBoard(storedBoard)
			}
		},
		async removeLabelFromCurrentBoard(label) {
			const deletedLabel = await apiClient.deleteLabel(label)
			const removeIndex = this.currentBoard?.labels?.findIndex((l) => deletedLabel.id === l.id)

			if (removeIndex > -1) {
				this.currentBoard.labels.splice(removeIndex, 1)
			}
		},
		async updateLabelFromCurrentBoard(newLabel) {
			const updated = await apiClient.updateLabel(newLabel)
			const labelToUpdate = this.currentBoard?.labels?.find((l) => updated.id === l.id)

			if (labelToUpdate) {
				labelToUpdate.title = updated.title
				labelToUpdate.color = updated.color
			}
		},
		async addLabelToCurrentBoard(newLabel) {
			newLabel.boardId = this.currentBoard.id
			const created = await apiClient.createLabel(newLabel)
			this.currentBoard?.labels?.push(created)
		},
		async addLabelToCurrentBoardAndCard({ newLabel, card }) {
			newLabel.boardId = this.currentBoard.id
			const label = await apiClient.createLabel(newLabel)
			card.labels.push(label)
			this.currentBoard?.labels?.push(label)
			await useCardStore().addLabel({
				card,
				labelId: label.id,
			})
			return label
		},
		async addAclToCurrentBoard(newAcl) {
			newAcl.boardId = this.currentBoard.id
			const result = await apiClient.addAcl(newAcl)
			this.currentBoard?.acl?.push(result)
			this.refreshBoard(newAcl.boardId)
		},
		async updateAclFromCurrentBoard(acl) {
			acl.boardId = this.currentBoard.id
			const updatedAcl = await apiClient.updateAcl(acl)
			const updateIndex = this.currentBoard?.acl?.findIndex((currentAcl) => {
				return currentAcl.participant.uid === updatedAcl.participant.uid
			})
			if (updateIndex > -1) {
				Vue.set(this.currentBoard.acl, updateIndex, updatedAcl)
			}
		},
		async deleteAclFromCurrentBoard(acl) {
			acl.boardId = this.currentBoard.id
			const deletedAcl = await apiClient.deleteAcl(acl)
			const removeIndex = this.currentBoard?.acl?.findIndex((attr) => deletedAcl.id === attr.id)

			if (removeIndex > -1) {
				Vue.delete(this.currentBoard.acl, removeIndex)
			}
			this.loadBoardById(deletedAcl.boardId)
		},
		async transferOwnership({ boardId, newOwner }) {
			await axios.put(generateUrl(`apps/deck/boards/${boardId}/transferOwner`), {
				newOwner,
			})
		},
	},
})
