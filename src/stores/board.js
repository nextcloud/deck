/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import axios from '@nextcloud/axios'
import { loadState } from '@nextcloud/initial-state'
import { generateUrl } from '@nextcloud/router'
import { defineStore } from 'pinia'
import { SWIMLANE_ORDER_KEY } from '../constants.js'
import { BoardApi } from '../services/BoardApi.js'
import { useCardStore } from './card.js'
import { useSettingsStore } from './settings.js'
import { useStackStore } from './stack.js'

const apiClient = new BoardApi()
// Write state per swimlane config key. `last` counts started writes, so a failed
// write does not undo a newer one. `pending` counts writes still in flight.
const swimlaneWrites = {}

function swimlaneConfigKey(boardId, key) {
	return `board:${boardId}:${key}`
}

function swimlaneWriteState(configKey) {
	if (!swimlaneWrites[configKey]) {
		swimlaneWrites[configKey] = { last: 0, pending: 0 }
	}
	return swimlaneWrites[configKey]
}

// Write state of every swimlane setting of a board, taken before a load starts.
function snapshotSwimlaneWrites(boardId) {
	const snapshot = {}
	for (const key of ['swimlaneMode', ...Object.values(SWIMLANE_ORDER_KEY)]) {
		const configKey = swimlaneConfigKey(boardId, key)
		snapshot[configKey] = { ...swimlaneWriteState(configKey) }
	}
	return snapshot
}

// A load must not overwrite a setting whose write was open when the load started,
// or started while it ran. The answer may predate that write.
function isSwimlaneWriteProtected(snapshot, configKey) {
	const before = snapshot?.[configKey]
	if (!before) {
		return false
	}
	return before.pending > 0 || swimlaneWriteState(configKey).last !== before.last
}

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
		swimlaneLabelOrder: {},
		swimlaneUserOrder: {},
		assignableUsers: [],
		filter: { tags: [], users: [], due: '', unassigned: false, completed: 'both' },
		boardFilter: BOARD_FILTERS.ALL,
		boards: loadState('deck', 'initialBoards', {}),
	}),
	getters: {
		boardById: (state) => (id) => {
			return state.boards.find((board) => board.id === id)
		},
		viewMode(state) {
			if (!state.currentBoard) {
				return 'kanban'
			}
			if (state.viewModeByBoard[state.currentBoard.id] !== undefined) {
				return state.viewModeByBoard[state.currentBoard.id]
			}

			const stored = localStorage.getItem(`deck.viewMode.${state.currentBoard.id}`)
			return stored !== null ? stored : 'kanban'
		},
		assignables: (state) => {
			if (!state.currentBoard) {
				return []
			}

			return [
				...state.assignableUsers.map((user) => ({ ...user, type: user.type })),
				...state.currentBoard.acl.filter((acl) => acl.type === 1 && typeof acl.participant === 'object').map((group) => ({ ...group.participant, type: 1 })),
				...state.currentBoard.acl.filter((acl) => acl.type === 7 && typeof acl.participant === 'object').map((circle) => ({ ...circle.participant, type: 7 })),
			]
		},
		noneArchivedBoards: (state) => {
			return state.boards.filter((board) => {
				return board.archived === false && !board.deletedAt
			})
		},
		archivedBoards: (state) => {
			return state.boards.filter((board) => {
				return board.archived === true && !board.deletedAt
			})
		},
		sharedBoards: (state) => {
			return state.boards.filter((board) => {
				return board.shared && !board.deletedAt
			})
		},
		filteredBoards: (state) => {
			// filters the boards depending on the active filter
			const boards = state.boards.filter((board) => {
				return (state.boardFilter === BOARD_FILTERS.ALL && board.archived === false)
					|| (state.boardFilter === BOARD_FILTERS.ARCHIVED && board.archived === true)
					|| (state.boardFilter === BOARD_FILTERS.SHARED && board.shared === 1)
			})
			return boards
		},
		currentBoardLabels: (state) => {
			return state.currentBoard ? state.currentBoard.labels : []
		},
		swimlaneMode: (state) => {
			return state.currentBoard?.settings?.swimlaneMode || 'none'
		},
		canEdit: (state) => {
			return state.currentBoard ? state.currentBoard.permissions.PERMISSION_EDIT : false
		},
		canManage: (state) => {
			return state.currentBoard ? state.currentBoard.permissions.PERMISSION_MANAGE : false
		},
		canShare: (state) => {
			return state.currentBoard ? state.currentBoard.permissions.PERMISSION_SHARE : false
		},
		isArchived: (state) => {
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
			if (!this.currentBoard) {
				return
			}
			this.viewModeByBoard[this.currentBoard.id] = mode
			localStorage.setItem(`deck.viewMode.${this.currentBoard.id}`, mode)
		},
		async loadBoardById(boardId) {
			this.filter = { tags: [], users: [], due: '', unassigned: false, completed: 'both' }
			this.setCurrentBoard(null)
			const board = await apiClient.loadById(boardId)
			this.setCurrentBoard(board)
			this.setAssignableUsers(board.users)
			this.readSwimlaneSettings(boardId, board)
		},
		// Shared board settings, read on every load so other members' changes arrive.
		// refreshBoard passes a snapshot. Settings with a write open at the snapshot or started since keep the local value.
		readSwimlaneSettings(boardId, board, snapshot) {
			if (!board.settings) {
				return
			}
			if (isSwimlaneWriteProtected(snapshot, swimlaneConfigKey(boardId, 'swimlaneMode'))
				&& this.currentBoard?.id === boardId && this.currentBoard.settings) {
				board.settings.swimlaneMode = this.currentBoard.settings.swimlaneMode
			}
			for (const type of Object.keys(SWIMLANE_ORDER_KEY)) {
				if (isSwimlaneWriteProtected(snapshot, swimlaneConfigKey(boardId, SWIMLANE_ORDER_KEY[type]))) {
					continue
				}
				try {
					const parsed = JSON.parse(board.settings[SWIMLANE_ORDER_KEY[type]] || '[]')
					if (Array.isArray(parsed)) {
						this[SWIMLANE_ORDER_KEY[type]][boardId] = parsed
					}
				} catch { /* ignore malformed saved order */ }
			}
		},
		async refreshBoard(boardId) {
			const snapshot = snapshotSwimlaneWrites(boardId)
			const board = await apiClient.loadById(boardId)
			const etagHasChanged = board.ETag !== this.currentBoard?.ETag
			// Before setCurrentBoard, so the kept mode lands in the new board object.
			this.readSwimlaneSettings(boardId, board, snapshot)
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
					this.boards[indexExisting] = newBoard
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
				this.boards[indexExisting] = board
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
				this.currentBoard.acl[updateIndex] = updatedAcl
			}
		},
		async deleteAclFromCurrentBoard(acl) {
			acl.boardId = this.currentBoard.id
			const deletedAcl = await apiClient.deleteAcl(acl)
			const removeIndex = this.currentBoard?.acl?.findIndex((attr) => deletedAcl.id === attr.id)

			if (removeIndex > -1) {
				this.currentBoard.acl.splice(removeIndex, 1)
			}
			this.loadBoardById(deletedAcl.boardId)
		},
		async transferOwnership({ boardId, newOwner }) {
			await axios.put(generateUrl(`apps/deck/boards/${boardId}/transferOwner`), {
				newOwner,
			})
		},
		// Optimistic update, rolled back if the server rejects the write.
		async setSwimlaneMode({ boardId, mode }) {
			const configKey = swimlaneConfigKey(boardId, 'swimlaneMode')
			const state = swimlaneWriteState(configKey)
			const write = ++state.last
			// Only touch the board the write is for, the user may open another one meanwhile.
			const isCurrent = () => this.currentBoard?.id === boardId && this.currentBoard.settings
			const previous = this.currentBoard?.settings?.swimlaneMode || 'none'
			if (isCurrent()) {
				this.currentBoard.settings.swimlaneMode = mode
			}
			state.pending++
			try {
				await useSettingsStore().setConfig({ [configKey]: mode })
			} catch (e) {
				// Roll back only if no newer write started meanwhile.
				if (state.last === write && isCurrent()) {
					this.currentBoard.settings.swimlaneMode = previous
				}
				throw e
			} finally {
				state.pending--
			}
		},
		async setSwimlaneOrder({ boardId, type, order }) {
			const key = SWIMLANE_ORDER_KEY[type]
			const configKey = swimlaneConfigKey(boardId, key)
			const state = swimlaneWriteState(configKey)
			const write = ++state.last
			const previous = this[key][boardId] ? [...this[key][boardId]] : []
			this[key][boardId] = order
			let saved
			state.pending++
			try {
				const result = await useSettingsStore().setConfig({ [configKey]: JSON.stringify(order) })
				saved = result?.[configKey]
			} catch (e) {
				// Roll back only if no newer write started meanwhile.
				if (state.last === write) {
					this[key][boardId] = previous
				}
				throw e
			} finally {
				state.pending--
			}
			// The server answers with the order it stored. It drops entries it
			// cannot keep, so take that list unless a newer write started. An
			// answer that is not a list keeps the optimistic order, the write itself worked.
			if (state.last !== write || typeof saved !== 'string') {
				return
			}
			let parsed = null
			try {
				parsed = JSON.parse(saved)
			} catch {
				// Not JSON, the optimistic order stays.
			}
			if (Array.isArray(parsed)) {
				this[key][boardId] = parsed
			}
		},
	},
})
