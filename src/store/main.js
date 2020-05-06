/*
 * @copyright Copyright (c) 2018 Julius Härtl <jus@bitgrid.net>
 *
 * @author Julius Härtl <jus@bitgrid.net>
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
 */

import 'url-search-params-polyfill'

import Vue from 'vue'
import Vuex from 'vuex'
import axios from '@nextcloud/axios'
import { BoardApi } from './../services/BoardApi'
import stack from './stack'
import card from './card'
import comment from './comment'
import trashbin from './trashbin'
import attachment from './attachment'
import debounce from 'lodash/debounce'
Vue.use(Vuex)

const apiClient = new BoardApi()
const debug = process.env.NODE_ENV !== 'production'

export const BOARD_FILTERS = {
	ALL: '',
	ARCHIVED: 'archived',
	SHARED: 'shared',
}

export default new Vuex.Store({
	modules: {
		stack,
		card,
		comment,
		trashbin,
		attachment,
	},
	strict: debug,
	state: {
		showArchived: false,
		navShown: true,
		compactMode: false,
		sidebarShown: false,
		currentBoard: null,
		currentCard: null,
		boards: [],
		sharees: [],
		assignableUsers: [],
		boardFilter: BOARD_FILTERS.ALL,
		searchQuery: '',
		activity: [],
		activityLoadMore: true,
		filter: { tags: [], users: [], due: '' },
	},
	getters: {
		getSearchQuery: state => {
			return state.searchQuery
		},
		getFilter: state => {
			return state.filter
		},
		boards: state => {
			return state.boards
		},
		assignables: state => {
			return [
				...state.assignableUsers.map((user) => ({ ...user, type: 0 })),
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
	},
	mutations: {
		setSearchQuery(state, searchQuery) {
			state.searchQuery = searchQuery
		},
		setFilter(state, filter) {
			Object.assign(state.filter, filter)
		},
		toggleShowArchived(state) {
			state.showArchived = !state.showArchived
		},
		/*
		 * Adds or replaces a board in the store.
		 * Matches a board by it's id.
		 */
		addBoard(state, board) {
			const indexExisting = state.boards.findIndex((b) => {
				return board.id === b.id
			})

			if (indexExisting > -1) {
				Vue.set(state.boards, indexExisting, board)
			} else {
				state.boards.push(board)
			}
		},

		cloneBoard(state, board) {
			const indexExisting = state.boards.findIndex((b) => {
				return board.id === b.id
			})

			if (indexExisting > -1) {
				Vue.set(state.boards, indexExisting, board)
			} else {
				state.boards.push(board)
			}
		},

		/*
		 * Removes the board from the store.
		 */
		removeBoard(state, board) {
			state.boards = state.boards.filter((b) => {
				return board.id !== b.id
			})
		},
		toggleNav(state) {
			state.navShown = !state.navShown
		},
		toggleSidebar(state) {
			state.sidebarShown = !state.sidebarShown
		},
		toggleCompactMode(state) {
			state.compactMode = !state.compactMode
		},
		setBoards(state, boards) {
			state.boards = boards
		},
		setSharees(state, shareesUsersAndGroups) {
			Vue.set(state, 'sharees', shareesUsersAndGroups.exact.users)
			state.sharees.push(...shareesUsersAndGroups.exact.groups)
			state.sharees.push(...shareesUsersAndGroups.exact.circles)

			state.sharees.push(...shareesUsersAndGroups.users)
			state.sharees.push(...shareesUsersAndGroups.groups)
			state.sharees.push(...shareesUsersAndGroups.circles)
		},
		setAssignableUsers(state, users) {
			state.assignableUsers = users
		},
		setBoardFilter(state, filter) {
			state.boardFilter = filter
		},
		setCurrentBoard(state, board) {
			state.currentBoard = board
		},
		setCurrentCard(state, card) {
			state.currentCard = card
		},

		// label mutators
		removeLabelFromCurrentBoard(state, labelId) {
			const removeIndex = state.currentBoard.labels.findIndex((l) => {
				return labelId === l.id
			})

			if (removeIndex > -1) {
				state.currentBoard.labels.splice(removeIndex, 1)
			}
		},
		updateLabelFromCurrentBoard(state, newLabel) {
			const labelToUpdate = state.currentBoard.labels.find((l) => {
				return newLabel.id === l.id
			})

			labelToUpdate.title = newLabel.title
			labelToUpdate.color = newLabel.color
		},
		addLabelToCurrentBoard(state, newLabel) {

			state.currentBoard.labels.push(newLabel)
		},

		// acl mutators
		addAclToCurrentBoard(state, createdAcl) {
			state.currentBoard.acl.push(createdAcl)
		},
		updateAclFromCurrentBoard(state, acl) {
			for (const acl_ in state.currentBoard.acl) {
				if (state.currentBoard.acl[acl_].participant.uid === acl.participant.uid) {
					Vue.set(state.currentBoard.acl, acl_, acl)
					break
				}
			}
		},
		deleteAclFromCurrentBoard(state, acl) {
			let removeIndex = -1
			for (const index in state.currentBoard.acl) {
				const attr = state.currentBoard.acl[index]
				if (acl.id === attr.id) {
					removeIndex = index
					break
				}
			}

			if (removeIndex > -1) {
				Vue.delete(state.currentBoard.acl, removeIndex)
			}
		},
	},
	actions: {
		setFilter({ commit }, filter) {
			commit('setFilter', filter)
		},
		async loadBoardById({ commit }, boardId) {
			commit('setCurrentBoard', null)
			const board = await apiClient.loadById(boardId)
			commit('setCurrentBoard', board)
			commit('setAssignableUsers', board.users)
		},

		async refreshBoard({ commit }, boardId) {
			const board = await apiClient.loadById(boardId)
			commit('setCurrentBoard', board)
			commit('setAssignableUsers', board.users)
		},

		toggleShowArchived({ commit }) {
			commit('toggleShowArchived')
		},

		/**
		 * @param commit
		 * @param state
		 * @param {Board} board
		 */
		archiveBoard({ commit }, board) {
			const boardCopy = JSON.parse(JSON.stringify(board))
			boardCopy.archived = true
			apiClient.updateBoard(boardCopy)
				.then((board) => {
					commit('addBoard', board)
				})
		},
		/**
		 * @param commit
		 * @param state
		 * @param {Board} board
		 */
		unarchiveBoard({ commit }, board) {
			const boardCopy = JSON.parse(JSON.stringify(board))
			boardCopy.archived = false
			apiClient.updateBoard(boardCopy)
				.then((board) => {
					commit('addBoard', board)
				})
		},
		/**
		 * Updates a board API side.
		 *
		 * @param commit
		 * @param board The board to update.
		 * @returns {Promise<void>}
		 */
		async updateBoard({ commit }, board) {
			const storedBoard = await apiClient.updateBoard(board)
			commit('addBoard', storedBoard)
		},
		createBoard({ commit }, boardData) {
			apiClient.createBoard(boardData)
				.then((board) => {
					commit('addBoard', board)
				})
		},
		async cloneBoard({ commit }, boardData) {
			try {
				const newBoard = await apiClient.cloneBoard(boardData)
				commit('cloneBoard', newBoard)
				return newBoard
			} catch (err) {
				return err
			}
		},
		removeBoard({ commit }, board) {
			commit('removeBoard', board)
		},
		async loadBoards({ commit }) {
			const boards = await apiClient.loadBoards()
			commit('setBoards', boards)
		},
		loadSharees: debounce(function({ commit }, query) {
			const params = new URLSearchParams()
			if (typeof query === 'undefined') {
				return
			}
			params.append('search', query)
			params.append('format', 'json')
			params.append('perPage', 20)
			params.append('itemType', [0, 1, 7])

			axios.get(OC.linkToOCS('apps/files_sharing/api/v1') + 'sharees', { params }).then((response) => {
				commit('setSharees', response.data.ocs.data)
			})
		}, 250),

		setBoardFilter({ commmit }, filter) {
			commmit('setBoardFilter', filter)
		},
		toggleNav({ commit }) {
			commit('toggleNav')
		},
		toggleSidebar({ commit }) {
			commit('toggleSidebar')
		},
		toggleCompactMode({ commit }) {
			commit('toggleCompactMode')
		},
		setCurrentBoard({ commit }, board) {
			commit('setCurrentBoard', board)
		},
		setAssignableUsers({ commit }, board) {
			commit('setAssignableUsers', board)
		},
		setCurrentCard({ commit }, card) {
			commit('setCurrentCard', card)
		},

		// label actions
		removeLabelFromCurrentBoard({ commit }, label) {
			apiClient.deleteLabel(label)
				.then((label) => {
					commit('removeLabelFromCurrentBoard', label.id)
				})
		},
		updateLabelFromCurrentBoard({ commit }, newLabel) {
			apiClient.updateLabel(newLabel)
				.then((newLabel) => {
					commit('updateLabelFromCurrentBoard', newLabel)
				})
		},
		addLabelToCurrentBoard({ commit }, newLabel) {
			newLabel.boardId = this.state.currentBoard.id
			apiClient.createLabel(newLabel)
				.then((newLabel) => {
					commit('addLabelToCurrentBoard', newLabel)
				})
		},

		// acl actions
		addAclToCurrentBoard({ dispatch, commit }, newAcl) {
			newAcl.boardId = this.state.currentBoard.id
			apiClient.addAcl(newAcl)
				.then((returnAcl) => {
					commit('addAclToCurrentBoard', returnAcl)
					dispatch('refreshBoard', newAcl.boardId)
				})
		},
		updateAclFromCurrentBoard({ commit }, acl) {
			acl.boardId = this.state.currentBoard.id
			apiClient.updateAcl(acl)
				.then((acl) => {
					commit('updateAclFromCurrentBoard', acl)
				})
		},
		deleteAclFromCurrentBoard({ dispatch, commit }, acl) {
			acl.boardId = this.state.currentBoard.id
			apiClient.deleteAcl(acl)
				.then((acl) => {
					commit('deleteAclFromCurrentBoard', acl)
					dispatch('loadBoardById', acl.boardId)
				})
		},
	},
})
