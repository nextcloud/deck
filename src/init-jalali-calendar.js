/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { subscribe } from '@nextcloud/event-bus'
import moment from '@nextcloud/moment'
import 'moment-jalaali'
import { 
	isPersianLocale, 
	getCurrentCalendarType,
	getPersianDayNames,
	getPersianMonthNames,
	getPersianShortDayNames,
	getPersianShortMonthNames,
	getPersianFirstDayOfWeek
} from './helpers/jalaliCalendar.js'

// Import the existing calendar initialization
import './init-calendar.js'

/**
 * Initialize Jalali calendar support
 */
function initJalaliCalendar() {
	// Check if the current locale is Persian
	if (isPersianLocale()) {
		console.log('[deck] Initializing Jalali calendar support for Persian locale')
		
		// Set up Persian locale for moment.js
		moment.locale('fa', {
			months: getPersianMonthNames(),
			monthsShort: getPersianShortMonthNames(),
			weekdays: getPersianDayNames(),
			weekdaysShort: getPersianShortDayNames(),
			weekdaysMin: getPersianShortDayNames(),
			weekdaysParseExact: true,
			longDateFormat: {
				LT: 'HH:mm',
				LTS: 'HH:mm:ss',
				L: 'jYYYY/jM/jD',
				LL: 'jYYYY jMMMM jD',
				LLL: 'jYYYY jMMMM jD HH:mm',
				LLLL: 'dddd jYYYY jMMMM jD HH:mm'
			},
			calendar: {
				sameDay: '[امروز ساعت] LT',
				nextDay: '[فردا ساعت] LT',
				nextWeek: 'dddd [ساعت] LT',
				lastDay: '[دیروز ساعت] LT',
				lastWeek: 'dddd [ساعت] LT',
				sameElse: 'L'
			},
			relativeTime: {
				future: 'در %s',
				past: '%s پیش',
				s: 'چند ثانیه',
				ss: '%d ثانیه',
				m: 'یک دقیقه',
				mm: '%d دقیقه',
				h: 'یک ساعت',
				hh: '%d ساعت',
				d: 'یک روز',
				dd: '%d روز',
				w: 'یک هفته',
				ww: '%d هفته',
				M: 'یک ماه',
				MM: '%d ماه',
				y: 'یک سال',
				yy: '%d سال'
			},
			ordinal: function (number) {
				return number
			},
			week: {
				dow: getPersianFirstDayOfWeek(), // Saturday is the first day of week
				doy: 7 // The week that contains Jan 1st is the first week of the year
			}
		})
		
		// Set the current locale to Persian
		moment.locale('fa')
		
		// Override Nextcloud's localization functions for Persian locale
		if (window.OC && window.OC.L10N) {
			// Store original functions
			const originalGetDayNamesMin = window.OC.L10N.getDayNamesMin
			const originalGetMonthNamesShort = window.OC.L10N.getMonthNamesShort
			const originalGetFirstDay = window.OC.L10N.getFirstDay
			
			// Override day names for Persian locale
			window.OC.L10N.getDayNamesMin = function() {
				if (isPersianLocale()) {
					return getPersianShortDayNames()
				}
				return originalGetDayNamesMin ? originalGetDayNamesMin() : []
			}
			
			// Override month names for Persian locale
			window.OC.L10N.getMonthNamesShort = function() {
				if (isPersianLocale()) {
					return getPersianShortMonthNames()
				}
				return originalGetMonthNamesShort ? originalGetMonthNamesShort() : []
			}
			
			// Override first day of week for Persian locale
			window.OC.L10N.getFirstDay = function() {
				if (isPersianLocale()) {
					return getPersianFirstDayOfWeek()
				}
				return originalGetFirstDay ? originalGetFirstDay() : 1
			}
		}
	}
}

/**
 * Subscribe to locale change events to reinitialize calendar
 */
subscribe('locale:changed', (locale) => {
	console.log('[deck] Locale changed to:', locale)
	// Reinitialize calendar support for the new locale
	setTimeout(initJalaliCalendar, 100)
})

/**
 * Initialize Jalali calendar support when the app loads
 */
document.addEventListener('DOMContentLoaded', () => {
	initJalaliCalendar()
})

// Also initialize immediately if DOM is already loaded
if (document.readyState === 'loading') {
	document.addEventListener('DOMContentLoaded', initJalaliCalendar)
} else {
	initJalaliCalendar()
}

/**
 * Export calendar type for use in components
 */
export { getCurrentCalendarType, isPersianLocale }
