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

import Vue from 'vue'
import Vuex from 'vuex'
import { boardToMenuItem } from './../helpers/boardToMenuItem'
import { BoardApi } from './../services/BoardApi'

Vue.use(Vuex)

const apiClient = new BoardApi()
const debug = process.env.NODE_ENV !== 'production'

export const BOARD_FILTERS = {
	ALL: '',
	ARCHIVED: 'archived',
	SHARED: 'shared'
}

export default new Vuex.Store({
	modules: {},
	strict: debug,
	state: {
		navShown: true,
		sidebarShown: false,
		currentBoard: null,
		boards: [],
		boardFilter: BOARD_FILTERS.ALL
	},
	getters: {
		boards: state => {
			return state.boards
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
			return boards.map(boardToMenuItem)
		}
	},
	mutations: {
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
		/**
		 * Removes the board from the store.
		 *
		 * @param state
		 * @param board
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
		setBoards(state, boards) {
			state.boards = boards
		},
		setBoardFilter(state, filter) {
			state.boardFilter = filter
		},
		setCurrentBoard(state, board) {
			state.currentBoard = board
		}
	},
	actions: {
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
		createBoard({ commit }, boardData) {
			apiClient.createBoard(boardData)
				.then((board) => {
					commit('addBoard', board)
				})
		},
		removeBoard({ commit }, board) {
			commit('removeBoard', board)
		},
		setBoards({ commit }, boards) {
			commit('setBoards', boards)
		},
		setBoardFilter({ commmit }, filter) {
			commmit('setBoardFilter', filter)
		},
		toggleNav({ commit }) {
			commit('toggleNav')
		},
		toggleSidebar({ commit }) {
			commit('toggleSidebar')
		},
		setCurrentBoard({ commit }, board) {
			commit('setCurrentBoard', board)
		}
	}
})
