/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

/* eslint-env jest */

// `t` is a global provided by the server, the helper only ever passes strings through it
global.t = (app, text) => text

const {
	CSV_COLUMNS,
	buildBoardCsv,
	downloadBlob,
	formatIsoDate,
	formatTimestamp,
	toCsvBlob,
} = require('../boardExport.js')

/**
 * The formatted dates are in the timezone of the machine running the tests, so
 * they are compared as instants instead of as strings.
 *
 * @param {string} formatted ISO 8601 date returned by the helper
 * @return {string} the same instant in UTC
 */
const asUtc = formatted => new Date(formatted).toISOString()

const card = (overrides = {}) => ({
	id: 100,
	title: 'Card',
	description: '',
	order: 0,
	labels: [],
	assignedUsers: [],
	archived: false,
	done: null,
	duedate: null,
	startdate: null,
	createdAt: 0,
	lastModified: 0,
	commentsCount: 0,
	attachmentCount: 0,
	...overrides,
})

const board = (cards, stack = {}) => ({
	title: 'Board',
	stacks: [{ id: 10, title: 'To do', cards, ...stack }],
})

/**
 * A minimal RFC 4180 reader. Splitting on the separator would not do: a cell may
 * contain the separator, a quote or a line break, so parsing the output back is
 * also what proves it is well formed.
 *
 * @param {string} csv the generated CSV
 * @return {string[][]} the rows, split into their cells with the quoting removed
 */
const rows = csv => {
	const parsed = []
	let row = []
	let cell = ''
	let quoted = false

	for (let i = 0; i < csv.length; i++) {
		const character = csv[i]
		if (quoted) {
			if (character !== '"') {
				cell += character
			} else if (csv[i + 1] === '"') {
				cell += '"'
				i++
			} else {
				quoted = false
			}
		} else if (character === '"') {
			quoted = true
		} else if (character === ',') {
			row.push(cell)
			cell = ''
		} else if (character === '\r' && csv[i + 1] === '\n') {
			row.push(cell)
			cell = ''
			parsed.push(row)
			row = []
			i++
		} else {
			cell += character
		}
	}

	return parsed
}

describe('formatIsoDate', () => {
	it('keeps the exported instant', () => {
		expect(asUtc(formatIsoDate('2050-07-24T22:00:00+00:00'))).toBe('2050-07-24T22:00:00.000Z')
	})

	it('keeps the instant of a date exported with an offset', () => {
		expect(asUtc(formatIsoDate('2023-07-10T10:00:00+02:00'))).toBe('2023-07-10T08:00:00.000Z')
	})

	it('returns an offset that makes the local time unambiguous', () => {
		expect(formatIsoDate('2050-07-24T22:00:00+00:00')).toMatch(/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(Z|[+-]\d{2}:\d{2})$/)
	})

	it.each([null, undefined, ''])('renders %p as an empty cell', value => {
		expect(formatIsoDate(value)).toBe('')
	})

	it('renders an unparsable date as an empty cell', () => {
		expect(formatIsoDate('not a date')).toBe('')
	})
})

describe('formatTimestamp', () => {
	it('keeps the instant of a unix timestamp', () => {
		expect(asUtc(formatTimestamp(1689667796))).toBe('2023-07-18T08:09:56.000Z')
	})

	it('accepts a timestamp that was exported as a string', () => {
		expect(formatTimestamp('1689667796')).toBe(formatTimestamp(1689667796))
	})

	it.each([null, undefined, 0, '0', ''])('renders %p as an empty cell', value => {
		expect(formatTimestamp(value)).toBe('')
	})
})

describe('buildBoardCsv', () => {
	it('starts with a header row of every column', () => {
		const [header] = rows(buildBoardCsv(board([])))

		expect(header).toHaveLength(CSV_COLUMNS.length)
		expect(header).toContain('Card ID')
		expect(header).toContain('Card title')
		expect(header).toContain('Completed at')
	})

	it('writes one row per card, quoted and comma separated', () => {
		const csv = buildBoardCsv(board([card(), card({ id: 101 })]))

		expect(rows(csv)).toHaveLength(3)
		expect(csv.endsWith('\r\n')).toBe(true)
		expect(csv.split('\r\n')[1].startsWith('"')).toBe(true)
		expect(csv.split('\r\n')[1]).toContain('","')
	})

	it('keeps the header in English whatever the interface language is', () => {
		const translate = global.t
		global.t = () => 'ÜBERSETZT'

		try {
			const [header] = rows(buildBoardCsv(board([])))

			expect(header).toEqual([
				'Card ID', 'Card title', 'Description', 'List ID', 'List name', 'Order',
				'Tags', 'Assigned users', 'Archived', 'Completed', 'Completed at',
				'Due date', 'Start date', 'Created', 'Modified', 'Comments', 'Attachments',
				'Depends on card IDs',
			])
		} finally {
			global.t = translate
		}
	})

	it('exports the identifiers and the list a card belongs to', () => {
		const [header, row] = rows(buildBoardCsv(board([card()])))
		const cell = key => row[header.indexOf(CSV_COLUMNS.find(column => column.key === key).label)]

		expect(cell('cardId')).toBe('100')
		expect(cell('stackId')).toBe('10')
		expect(cell('stackTitle')).toBe('To do')
	})

	it('escapes quotes and carries separators inside a cell', () => {
		const csv = buildBoardCsv(board([card({
			title: 'He said "hi"',
			description: 'a,b;c\td',
		})]))

		expect(csv).toContain('"He said ""hi"""')
		expect(rows(csv)[1]).toContain('He said "hi"')
		expect(rows(csv)[1]).toContain('a,b;c\td')
	})

	it('carries a description that spans several lines', () => {
		const description = '# Heading\n\n- one\n- two'
		const parsed = rows(buildBoardCsv(board([card({ description }), card({ id: 101 })])))

		expect(parsed).toHaveLength(3)
		expect(parsed[1]).toContain(description)
		expect(parsed[2]).toContain('101')
	})

	it('joins labels and assigned users', () => {
		const csv = buildBoardCsv(board([card({
			labels: [{ title: 'Bug' }, { title: 'Feature' }],
			assignedUsers: [
				{ participant: { uid: 'alice', displayname: 'Alice' } },
				{ participant: { uid: 'bob' } },
				{ participant: 'carol' },
			],
		})]))

		expect(rows(csv)[1]).toContain('Bug, Feature')
		expect(rows(csv)[1]).toContain('Alice, bob, carol')
	})

	it('renders the flags of a card as 1 and 0 rather than localised words', () => {
		const [header, open, finished] = rows(buildBoardCsv(board([
			card(),
			card({ id: 101, archived: true, done: '2023-07-18T10:00:00+00:00' }),
		])))
		const cell = (row, key) => row[header.indexOf(CSV_COLUMNS.find(column => column.key === key).label)]

		expect(cell(open, 'archived')).toBe('0')
		expect(cell(open, 'done')).toBe('0')
		expect(cell(finished, 'archived')).toBe('1')
		expect(cell(finished, 'done')).toBe('1')
	})

	it('exports the completion date next to the completion flag', () => {
		const [header, row] = rows(buildBoardCsv(board([card({ done: '2023-07-18T10:00:00+00:00' })])))

		expect(asUtc(row[header.indexOf('Completed at')])).toBe('2023-07-18T10:00:00.000Z')
		expect(row[header.indexOf('Completed')]).toBe('1')
	})

	it('exports timestamps and dates in their own format', () => {
		const [header, row] = rows(buildBoardCsv(board([card({
			createdAt: 1689667796,
			duedate: '2050-07-24T22:00:00+00:00',
		})])))

		expect(asUtc(row[header.indexOf('Created')])).toBe('2023-07-18T08:09:56.000Z')
		expect(asUtc(row[header.indexOf('Due date')])).toBe('2050-07-24T22:00:00.000Z')
		expect(row[header.indexOf('Start date')]).toBe('')
	})

	it('exports counts as numbers and falls back to zero', () => {
		const [header, row] = rows(buildBoardCsv(board([card({ commentsCount: 3, attachmentCount: undefined })])))

		expect(row[header.indexOf('Comments')]).toBe('3')
		expect(row[header.indexOf('Attachments')]).toBe('0')
	})

	it('lists the cards a card depends on by id', () => {
		const [header, withDeps, without] = rows(buildBoardCsv(board([
			card({ dependentCards: [201, 202] }),
			card({ id: 101 }),
		])))

		expect(withDeps[header.indexOf('Depends on card IDs')]).toBe('201, 202')
		expect(without[header.indexOf('Depends on card IDs')]).toBe('')
	})

	it('covers every list of the board', () => {
		const csv = buildBoardCsv({
			title: 'Board',
			stacks: [
				{ id: 10, title: 'To do', cards: [card()] },
				{ id: 11, title: 'Done', cards: [card({ id: 200 })] },
			],
		})

		expect(rows(csv)[1]).toContain('To do')
		expect(rows(csv)[2]).toContain('Done')
		expect(rows(csv)[2]).toContain('200')
	})

	it('survives a board, a list or a card without content', () => {
		expect(rows(buildBoardCsv({}))).toHaveLength(1)
		expect(rows(buildBoardCsv({ stacks: [{ id: 10, title: 'To do' }] }))).toHaveLength(1)
		expect(rows(buildBoardCsv(board([{}])))).toHaveLength(2)
	})
})

describe('toCsvBlob', () => {
	const bytes = async text => new Uint8Array(await toCsvBlob(text).arrayBuffer())

	it('starts with the UTF-8 byte order mark', async () => {
		expect(Array.from(await bytes('A')).slice(0, 3)).toEqual([0xef, 0xbb, 0xbf])
	})

	it('encodes the text as UTF-8', async () => {
		expect(Array.from(await bytes('A,'))).toEqual([0xef, 0xbb, 0xbf, 65, 44])
	})

	it('keeps characters outside of latin1', async () => {
		expect(Array.from(await bytes('ü'))).toEqual([0xef, 0xbb, 0xbf, 0xc3, 0xbc])
	})

	it('is typed so that spreadsheet applications pick the right encoding', () => {
		expect(toCsvBlob('A').type).toBe('text/csv;charset=utf-8')
	})
})

describe('downloadBlob', () => {
	it('offers the blob under the given filename and releases it again', () => {
		const anchor = { click: jest.fn(), remove: jest.fn() }
		global.document = { createElement: jest.fn(() => anchor) }
		global.URL.createObjectURL = jest.fn(() => 'blob:url')
		global.URL.revokeObjectURL = jest.fn()

		const blob = new Blob(['x'])
		downloadBlob(blob, 'Board.csv')

		expect(global.URL.createObjectURL).toHaveBeenCalledWith(blob)
		expect(anchor.href).toBe('blob:url')
		expect(anchor.download).toBe('Board.csv')
		expect(anchor.click).toHaveBeenCalled()
		expect(anchor.remove).toHaveBeenCalled()
		expect(global.URL.revokeObjectURL).toHaveBeenCalledWith('blob:url')
	})
})
