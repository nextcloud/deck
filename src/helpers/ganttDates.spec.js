/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { describe, expect, it } from '@jest/globals'
import { computeDraggedDates, shiftDate } from './ganttDates.js'

const HOUR = 3600000
const DAY = 24 * HOUR

// Month view: one column is one 30 day month, snapping happens every 7 days
const MONTH_SCALE = { unit: 'month', step: 1, columnWidth: 120 }
const MONTH_SNAP_PX = 120 / (30 / 7)
// Week view: one column is 7 days, snapping happens every day
const WEEK_SCALE = { unit: 'day', step: 7, columnWidth: 140 }
// Day view: one column is 12 hours, snapping happens every hour
const DAY_SCALE = { unit: 'hour', step: 12, columnWidth: 38 }

function origin() {
	return {
		start: new Date(2026, 9, 30, 9, 30),
		end: new Date(2026, 10, 29, 17, 15),
		x: 472.23,
		width: 160.71,
	}
}

describe('shiftDate', () => {
	it('keeps the time of day when shifting by full days', () => {
		const result = shiftDate(new Date(2026, 9, 20, 9, 30), 10 * DAY)
		expect(result).toEqual(new Date(2026, 9, 30, 9, 30))
	})

	it('shifts by hours and minutes', () => {
		const result = shiftDate(new Date(2026, 9, 20, 9, 30), -(DAY + 2 * HOUR + 15 * 60000))
		expect(result).toEqual(new Date(2026, 9, 19, 7, 15))
	})

	it('rounds floating point noise to full minutes', () => {
		const result = shiftDate(new Date(2026, 9, 20, 9, 30), 7 * DAY - 0.0001)
		expect(result).toEqual(new Date(2026, 9, 27, 9, 30))
	})
})

describe('computeDraggedDates', () => {
	it('keeps the dates when the bar was not moved', () => {
		const o = origin()
		for (const scale of [MONTH_SCALE, WEEK_SCALE, DAY_SCALE]) {
			const { start, end } = computeDraggedDates(o, o.x, o.width, scale)
			expect(start).toEqual(o.start)
			expect(end).toEqual(o.end)
		}
	})

	it('moves both dates by the snapped distance in the month view', () => {
		const o = origin()
		const { start, end } = computeDraggedDates(o, o.x + 3 * MONTH_SNAP_PX, o.width, MONTH_SCALE)
		expect(start).toEqual(new Date(2026, 10, 20, 9, 30))
		expect(end).toEqual(new Date(2026, 11, 20, 17, 15))
	})

	it('only moves the end date when resizing on the right in the month view', () => {
		const o = origin()
		const { start, end } = computeDraggedDates(o, o.x, o.width + MONTH_SNAP_PX, MONTH_SCALE)
		expect(start).toEqual(o.start)
		expect(end).toEqual(new Date(2026, 11, 6, 17, 15))
	})

	it('only moves the start date when resizing on the left in the week view', () => {
		const o = origin()
		const { start, end } = computeDraggedDates(o, o.x - 40, o.width + 40, WEEK_SCALE)
		expect(start).toEqual(new Date(2026, 9, 28, 9, 30))
		expect(end).toEqual(o.end)
	})

	it('moves by hours in the day view', () => {
		const o = origin()
		const { start, end } = computeDraggedDates(o, o.x + 5 * 38 / 12, o.width, DAY_SCALE)
		expect(start).toEqual(new Date(2026, 9, 30, 14, 30))
		expect(end).toEqual(new Date(2026, 10, 29, 22, 15))
	})
})
