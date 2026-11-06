/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

const MS_PER_DAY = 86400000
const MS_PER_MINUTE = 60000

// Mirrors frappe-gantt date_utils.convert_scales() constants.
// DAYS_PER_UNIT[scale] = how many days one unit of that scale is.
export const DAYS_PER_UNIT = {
	millisecond: 1 / 86400000,
	second: 1 / 86400,
	minute: 1 / 1440,
	hour: 1 / 24,
	day: 1,
	month: 30,
	year: 365,
}

/**
 * Shift a date by a duration rounded to full minutes.
 * Full days are added on the calendar, so the time of day is kept across DST changes.
 *
 * @param {Date} date the date to shift
 * @param {number} ms the duration in milliseconds, may be negative
 * @return {Date}
 */
export function shiftDate(date, ms) {
	const minutes = Math.round(ms / MS_PER_MINUTE)
	const days = Math.trunc(minutes * MS_PER_MINUTE / MS_PER_DAY)
	const result = new Date(date)
	result.setDate(result.getDate() + days)
	result.setTime(result.getTime() + minutes * MS_PER_MINUTE - days * MS_PER_DAY)
	return result
}

/**
 * Compute the dates of a gantt bar from how far it was moved or resized since the drag started.
 *
 * @param {object} origin dates and position of the bar when the drag started
 * @param {Date} origin.start start date
 * @param {Date} origin.end end date
 * @param {number} origin.x left position in pixels
 * @param {number} origin.width width in pixels
 * @param {number} x current left position in pixels
 * @param {number} width current width in pixels
 * @param {object} scale the scale of the chart
 * @param {string} scale.unit time unit of one step, e.g. 'day'
 * @param {number} scale.step number of units per column
 * @param {number} scale.columnWidth width of one column in pixels
 * @return {{start: Date, end: Date}}
 */
export function computeDraggedDates(origin, x, width, { unit, step, columnWidth }) {
	const msPerPixel = DAYS_PER_UNIT[unit] * step * MS_PER_DAY / columnWidth
	return {
		start: shiftDate(origin.start, (x - origin.x) * msPerPixel),
		end: shiftDate(origin.end, (x + width - origin.x - origin.width) * msPerPixel),
	}
}
