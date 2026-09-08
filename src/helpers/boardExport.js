/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import moment from '@nextcloud/moment'

/**
 * Columns of the CSV export.
 *
 * The set is deliberately wide: a CSV export is used for reporting and for
 * external tools, so it carries the stable identifiers and every state and date
 * field a card has.
 *
 * The headers are deliberately NOT translated. An export is machine readable
 * output, and a report built on it would break as soon as a colleague with a
 * different interface language exported the same board. This matches the JSON
 * export, whose keys are the English property names.
 */
export const CSV_COLUMNS = [
	{ key: 'cardId', label: 'Card ID' },
	{ key: 'title', label: 'Card title' },
	{ key: 'description', label: 'Description' },
	{ key: 'stackId', label: 'List ID' },
	{ key: 'stackTitle', label: 'List name' },
	{ key: 'order', label: 'Order' },
	{ key: 'labels', label: 'Tags' },
	{ key: 'assignedUsers', label: 'Assigned users' },
	{ key: 'archived', label: 'Archived' },
	{ key: 'done', label: 'Completed' },
	{ key: 'doneAt', label: 'Completed at' },
	{ key: 'duedate', label: 'Due date' },
	{ key: 'startdate', label: 'Start date' },
	{ key: 'createdAt', label: 'Created' },
	{ key: 'lastModified', label: 'Modified' },
	{ key: 'commentsCount', label: 'Comments' },
	{ key: 'attachmentCount', label: 'Attachments' },
	{ key: 'dependentCards', label: 'Depends on card IDs' },
]

/**
 * Format a date the backend exported as ISO 8601 into ISO 8601 in the timezone
 * of the browser.
 *
 * The backend serialises dates in UTC. Emitting that raw value made due dates
 * look shifted by the UTC offset in spreadsheets, so every date column is
 * converted to local time and keeps its offset to stay unambiguous.
 *
 * @param {string|null} value ISO 8601 date as exported by the backend
 * @return {string} local ISO 8601 date, or an empty string
 */
export function formatIsoDate(value) {
	if (!value) {
		return ''
	}
	const date = moment(value)
	return date.isValid() ? date.format() : ''
}

/**
 * Format a unix timestamp into ISO 8601 in the timezone of the browser.
 *
 * @param {number|string|null} value unix timestamp in seconds
 * @return {string} local ISO 8601 date, or an empty string
 */
export function formatTimestamp(value) {
	const timestamp = Number(value)
	if (!timestamp) {
		return ''
	}
	return moment.unix(timestamp).format()
}

/**
 * Booleans are written as 1/0 rather than a localised Yes/No, so that a report
 * built on an export does not break when the exporting user has another
 * language.
 *
 * @param {boolean} value the value to render
 * @return {string}
 */
function formatBoolean(value) {
	return value ? '1' : '0'
}

/**
 * @param {object} card the exported card
 * @param {object} stack the stack the card belongs to
 * @param {string} key column key
 * @return {string}
 */
function cellValue(card, stack, key) {
	switch (key) {
	case 'cardId':
		return String(card.id ?? '')
	case 'stackId':
		return String(stack.id ?? '')
	case 'stackTitle':
		return stack.title ?? ''
	case 'labels':
		return (card.labels ?? []).map(label => label.title).join(', ')
	case 'assignedUsers':
		return (card.assignedUsers ?? [])
			.map(assignment => assignment.participant?.displayname ?? assignment.participant?.uid ?? assignment.participant ?? '')
			.join(', ')
	case 'archived':
		return formatBoolean(card.archived)
	case 'done':
		return formatBoolean(!!card.done)
	case 'doneAt':
		return formatIsoDate(card.done)
	case 'duedate':
	case 'startdate':
		return formatIsoDate(card[key])
	case 'createdAt':
	case 'lastModified':
		return formatTimestamp(card[key])
	case 'dependentCards':
		return (card.dependentCards ?? []).join(', ')
	case 'commentsCount':
	case 'attachmentCount':
	case 'order':
		return String(card[key] ?? 0)
	default:
		return card[key] ?? ''
	}
}

/**
 * Build the card list of a board as RFC 4180 CSV.
 *
 * Every field is quoted and inner quotes are doubled, so separators, semicolons
 * and the line breaks of a markdown description are all safe to carry.
 *
 * @param {object} board board as returned by the export endpoint
 * @return {string}
 */
export function buildBoardCsv(board) {
	const escape = value => '"' + String(value).replaceAll('"', '""') + '"'
	const toRow = cells => cells.map(escape).join(',') + '\r\n'

	let csv = toRow(CSV_COLUMNS.map(column => column.label))
	for (const stack of board.stacks ?? []) {
		for (const card of stack.cards ?? []) {
			csv += toRow(CSV_COLUMNS.map(column => cellValue(card, stack, column.key)))
		}
	}

	return csv
}

/**
 * Wrap CSV text as a UTF-8 file with a byte order mark, which spreadsheet
 * applications need to recognise the encoding.
 *
 * @param {string} text the CSV text
 * @return {Blob}
 */
export function toCsvBlob(text) {
	return new Blob(['\ufeff' + text], { type: 'text/csv;charset=utf-8' })
}

/**
 * Offer a blob to the user as a file download.
 *
 * @param {Blob} blob the file content
 * @param {string} filename the name to save it under
 */
export function downloadBlob(blob, filename) {
	const blobUrl = URL.createObjectURL(blob)
	const a = document.createElement('a')
	a.href = blobUrl
	a.download = filename
	a.click()
	a.remove()
	URL.revokeObjectURL(blobUrl)
}
