/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import moment from '@nextcloud/moment'
import { 
	formatDate, 
	getRelativeTime, 
	isPersianLocale,
	getCurrentCalendarType,
	formatJalaliDate,
	getReadableJalaliDate
} from '../helpers/jalaliCalendar.js'

export default {
	computed: {
		/**
		 * Enhanced date formatting that supports both Gregorian and Jalali calendars
		 * @param {Date|string|moment} timestamp - The timestamp to format
		 * @param {string} format - Optional format string
		 * @returns {string} Formatted date string
		 */
		formatEnhancedDate() {
			return (timestamp, format = 'lll') => {
				if (!timestamp) return ''
				
				const calendarType = getCurrentCalendarType()
				
				if (calendarType === 'jalali') {
					return formatJalaliDate(timestamp, format)
				}
				
				return moment(timestamp).format(format)
			}
		},

		/**
		 * Enhanced readable date formatting with Jalali support
		 * @param {Date|string|moment} timestamp - The timestamp to format
		 * @returns {string} Readable date string
		 */
		formatReadableDate() {
			return (timestamp) => {
				if (!timestamp) return ''
				
				const calendarType = getCurrentCalendarType()
				
				if (calendarType === 'jalali') {
					return getReadableJalaliDate(timestamp)
				}
				
				return moment(timestamp).format('lll')
			}
		},

		/**
		 * Enhanced relative date formatting with Jalali support
		 * @param {Date|string|moment} timestamp - The timestamp to format
		 * @returns {string} Relative date string
		 */
		formatRelativeDate() {
			return (timestamp) => {
				if (!timestamp) return ''
				
				const calendarType = getCurrentCalendarType()
				
				if (calendarType === 'jalali') {
					return getRelativeTime(timestamp)
				}
				
				return moment(timestamp).fromNow()
			}
		},

		/**
		 * Check if the current locale uses Persian calendar
		 * @returns {boolean}
		 */
		isPersianCalendar() {
			return isPersianLocale()
		},

		/**
		 * Get the current calendar type
		 * @returns {string} 'jalali' or 'gregorian'
		 */
		currentCalendarType() {
			return getCurrentCalendarType()
		},

		/**
		 * Format date for display in the current calendar system
		 * @param {Date|string|moment} timestamp - The timestamp to format
		 * @returns {string} Formatted date string
		 */
		formatDateForDisplay() {
			return (timestamp) => {
				if (!timestamp) return ''
				
				const calendarType = getCurrentCalendarType()
				
				if (calendarType === 'jalali') {
					// Use Persian format for Jalali calendar
					return formatJalaliDate(timestamp, 'jYYYY jMMMM jD')
				}
				
				// Use standard format for Gregorian calendar
				return moment(timestamp).format('LLL')
			}
		},

		/**
		 * Format time for display in the current calendar system
		 * @param {Date|string|moment} timestamp - The timestamp to format
		 * @returns {string} Formatted time string
		 */
		formatTimeForDisplay() {
			return (timestamp) => {
				if (!timestamp) return ''
				
				const calendarType = getCurrentCalendarType()
				
				if (calendarType === 'jalali') {
					// For Jalali calendar, show both Jalali date and time
					const jalaliDate = formatJalaliDate(timestamp, 'jYYYY/jM/jD')
					const time = moment(timestamp).format('HH:mm')
					return `${jalaliDate} ${time}`
				}
				
				// For Gregorian calendar, show standard time
				return moment(timestamp).format('LTS')
			}
		},

		/**
		 * Format date and time for display in the current calendar system
		 * @param {Date|string|moment} timestamp - The timestamp to format
		 * @returns {string} Formatted date and time string
		 */
		formatDateTimeForDisplay() {
			return (timestamp) => {
				if (!timestamp) return ''
				
				const calendarType = getCurrentCalendarType()
				
				if (calendarType === 'jalali') {
					// For Jalali calendar, show Jalali date with time
					const jalaliDate = formatJalaliDate(timestamp, 'jYYYY jMMMM jD')
					const time = moment(timestamp).format('HH:mm')
					return `${jalaliDate} ساعت ${time}`
				}
				
				// For Gregorian calendar, show standard date and time
				return moment(timestamp).format('LLLL')
			}
		}
	}
}
