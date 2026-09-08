/**
 * SPDX-FileCopyrightText: 2018 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import axios from '@nextcloud/axios'
import { generateOcsUrl, generateUrl } from '@nextcloud/router'
import '../models/index.js'
import { buildBoardCsv, downloadBlob, toCsvBlob } from '../helpers/boardExport.js'

/**
 * This class handles all the api communication with the Deck backend.
 */
export class BoardApi {

	url(url) {
		url = `/apps/deck${url}`
		return generateUrl(url)
	}

	ocsUrl(url) {
		url = `/apps/deck/api/v1.0${url}`
		return generateOcsUrl(url)
	}

	/**
	 * Updates a board.
	 *
	 * @param {Board} board the board object to update
	 * @return {Promise}
	 */
	updateBoard(board) {
		return axios.put(this.url(`/boards/${board.id}`), board)
			.then(
				(response) => {
					return Promise.resolve(response.data)
				},
				(err) => {
					return Promise.reject(err)
				},
			)
			.catch((err) => {
				return Promise.reject(err)
			})
	}

	/**
	 * Creates a new board.
	 *
	 * @typedef {object} BoardCreateObject
	 * @property {string} title
	 * @property {string} color
	 * @param {BoardCreateObject} boardData The board data to send.
	 *        color the hexadecimal color value formated /[0-9A-F]{6}/i
	 * @return {Promise}
	 */
	createBoard(boardData) {
		return axios.post(this.ocsUrl('/boards'), boardData)
			.then(
				(response) => {
					return Promise.resolve(response.data.ocs.data)
				},
				(err) => {
					return Promise.reject(err)
				},
			)
			.catch((err) => {
				return Promise.reject(err)
			})
	}

	deleteBoard(board) {
		return axios.delete(this.url(`/boards/${board.id}`))
			.then(
				() => {
					return Promise.resolve()
				},
				(err) => {
					return Promise.reject(err)
				},
			)
			.catch((err) => {
				return Promise.reject(err)
			})
	}

	unDeleteBoard(board) {
		return axios.post(this.url(`/boards/${board.id}/deleteUndo`))
			.then(
				(response) => {
					return Promise.resolve(response.data)
				},
				(err) => {
					return Promise.reject(err)
				},
			)
			.catch((err) => {
				return Promise.reject(err)
			})
	}

	leaveBoard(board) {
		return axios.post(this.url(`/boards/${board.id}/leave`))
			.then(
				() => {
					return Promise.resolve()
				},
				(err) => {
					return Promise.reject(err)
				},
			)
			.catch((err) => {
				return Promise.reject(err)
			})
	}

	loadBoards() {
		return axios.get(this.url('/boards'))
			.then(
				(response) => {
					return Promise.resolve(response.data)
				},
				(err) => {
					return Promise.reject(err)
				},
			)
			.catch((err) => {
				return Promise.reject(err)
			})
	}

	loadById(id) {
		return axios.get(this.ocsUrl(`/board/${id}`))
			.then(
				(response) => {
					return Promise.resolve(response.data.ocs.data)
				},
				(err) => {
					return Promise.reject(err)
				},
			)
			.catch((err) => {
				return Promise.reject(err)
			})
	}

	async cloneBoard(board, withCards = false, withAssignments = false, withLabels = false, withDueDate = false, moveCardsToLeftStack = false, restoreArchivedCards = false) {
		try {
			const response = await axios.post(this.url(`/boards/${board.id}/clone`), {
				withCards,
				withAssignments,
				withLabels,
				withDueDate,
				moveCardsToLeftStack,
				restoreArchivedCards,
			})
			return response.data
		} catch (err) {
			return err
		}
	}

	/**
	 * Export a board as a downloadable file.
	 *
	 * The backend returns the complete board, including archived cards,
	 * completion state, comments and attachments, so both formats are built
	 * from the same payload.
	 *
	 * @param {Board} board the board to export
	 * @param {string} format either `json` or `csv`
	 * @param {object} options which parts of the board to include
	 * @return {Promise}
	 */
	async exportBoard(board, format, options = {}) {
		const { archivedCards = true, comments = true, attachments = true } = options
		const response = await axios.get(this.url(`/boards/${board.id}/export`), {
			params: {
				archivedCards,
				comments,
				// A CSV only lists cards, so never pay for the attachment payload
				attachments: format === 'csv' ? false : attachments,
			},
		})
		const exportedBoard = response.data

		if (format === 'json') {
			const stacks = {}
			for (const stack of exportedBoard.stacks ?? []) {
				stacks[stack.id] = stack
			}
			const exportData = {
				boards: [{ ...exportedBoard, stacks }],
			}
			downloadBlob(
				new Blob([JSON.stringify(exportData, null, 2)], { type: 'application/json' }),
				exportedBoard.title + '.json',
			)
			return
		}

		downloadBlob(toCsvBlob(buildBoardCsv(exportedBoard)), exportedBoard.title + '.csv')
	}

	importBoard(file, options = {}) {
		const formData = new FormData()
		formData.append('file', file)
		for (const [key, value] of Object.entries(options)) {
			formData.append(key, value ? '1' : '0')
		}
		return axios.post(this.url('/boards/import'), formData, {
			headers: {
				'Content-Type': 'multipart/form-data',
			},
		})
			.then(
				(response) => {
					return Promise.resolve(response.data)
				},
				(err) => {
					return Promise.reject(err)
				},
			)
			.catch((err) => {
				return Promise.reject(err)
			})
	}

	// Label API Calls
	deleteLabel(id) {
		return axios.delete(this.url(`/labels/${id}`))
			.then(
				(response) => {
					return Promise.resolve(response.data)
				},
				(err) => {
					return Promise.reject(err)
				},
			)
			.catch((err) => {
				return Promise.reject(err)
			})
	}

	updateLabel(label) {
		return axios.put(this.url(`/labels/${label.id}`), label)
			.then(
				(response) => {
					return Promise.resolve(response.data)
				},
				(err) => {
					return Promise.reject(err)
				},
			)
			.catch((err) => {
				return Promise.reject(err)
			})
	}

	createLabel(labelData) {
		return axios.post(this.url('/labels'), labelData)
			.then(
				(response) => {
					return Promise.resolve(response.data)
				},
				(err) => {
					return Promise.reject(err)
				},
			)
			.catch((err) => {
				return Promise.reject(err)
			})
	}

	// Acl API Calls

	addAcl(acl) {
		return axios.post(this.ocsUrl(`/boards/${acl.boardId}/acl`), acl)
			.then(
				(response) => {
					return Promise.resolve(response.data.ocs.data)
				},
				(err) => {
					return Promise.reject(err)
				},
			)
			.catch((err) => {
				return Promise.reject(err)
			})
	}

	updateAcl(acl) {
		return axios.put(this.url(`/boards/${acl.boardId}/acl/${acl.id}`), acl)
			.then(
				(response) => {
					return Promise.resolve(response.data)
				},
				(err) => {
					return Promise.reject(err)
				},
			)
			.catch((err) => {
				return Promise.reject(err)
			})
	}

	deleteAcl(acl) {
		return axios.delete(this.url(`/boards/${acl.boardId}/acl/${acl.id}`))
			.then(
				(response) => {
					return Promise.resolve(response.data)
				},
				(err) => {
					return Promise.reject(err)
				},
			)
			.catch((err) => {
				return Promise.reject(err)
			})
	}

}
