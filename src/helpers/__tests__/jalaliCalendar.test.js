/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import moment from '@nextcloud/moment'
import 'moment-jalaali'
import {
	isPersianLocale,
	getCurrentCalendarType,
	toJalali,
	toGregorian,
	formatDate,
	getRelativeTime,
	getPersianDayNames,
	getPersianMonthNames,
	getPersianShortDayNames,
	getPersianShortMonthNames,
	getPersianFirstDayOfWeek,
	isJalaliToday,
	isJalaliTomorrow,
	getJalaliComponents,
	createJalaliDate,
	getCurrentJalaliDate,
	formatJalaliDate,
	getReadableJalaliDate
} from '../jalaliCalendar.js'

// Mock moment locale for testing
const originalLocale = moment.locale
const mockLocale = 'en'

beforeEach(() => {
	// Reset to default locale before each test
	moment.locale('en')
})

afterEach(() => {
	// Restore original locale after each test
	moment.locale(originalLocale)
})

describe('Jalali Calendar Helper Functions', () => {
	describe('isPersianLocale', () => {
		it('should return false for non-Persian locales', () => {
			moment.locale('en')
			expect(isPersianLocale()).toBe(false)
		})

		it('should return true for Persian locales', () => {
			moment.locale('fa')
			expect(isPersianLocale()).toBe(true)
		})
	})

	describe('getCurrentCalendarType', () => {
		it('should return gregorian for non-Persian locales', () => {
			moment.locale('en')
			expect(getCurrentCalendarType()).toBe('gregorian')
		})

		it('should return jalali for Persian locales', () => {
			moment.locale('fa')
			expect(getCurrentCalendarType()).toBe('jalali')
		})
	})

	describe('toJalali', () => {
		it('should convert Gregorian date to Jalali', () => {
			const gregorianDate = new Date('2024-01-15')
			const jalaliDate = toJalali(gregorianDate)
			
			expect(jalaliDate).toBeDefined()
			expect(jalaliDate.jYear()).toBe(1402)
			expect(jalaliDate.jMonth()).toBe(10) // 0-based month
			expect(jalaliDate.jDate()).toBe(25)
		})

		it('should handle null input', () => {
			expect(toJalali(null)).toBeNull()
		})
	})

	describe('toGregorian', () => {
		it('should convert Jalali date to Gregorian', () => {
			const jalaliDate = moment.jMoment('1402/10/25', 'jYYYY/jM/jD')
			const gregorianDate = toGregorian(jalaliDate)
			
			expect(gregorianDate).toBeDefined()
			expect(gregorianDate.year()).toBe(2024)
			expect(gregorianDate.month()).toBe(0) // 0-based month (January)
			expect(gregorianDate.date()).toBe(15)
		})

		it('should handle null input', () => {
			expect(toGregorian(null)).toBeNull()
		})
	})

	describe('formatDate', () => {
		it('should format date in Gregorian for non-Persian locale', () => {
			moment.locale('en')
			const date = new Date('2024-01-15')
			const formatted = formatDate(date, 'LLL')
			
			expect(formatted).toContain('Jan 15, 2024')
		})

		it('should format date in Jalali for Persian locale', () => {
			moment.locale('fa')
			const date = new Date('2024-01-15')
			const formatted = formatDate(date, 'LLL')
			
			expect(formatted).toContain('1402')
		})
	})

	describe('getRelativeTime', () => {
		it('should return relative time in Gregorian for non-Persian locale', () => {
			moment.locale('en')
			const date = moment().subtract(1, 'day')
			const relative = getRelativeTime(date)
			
			expect(relative).toContain('ago')
		})

		it('should return relative time in Jalali for Persian locale', () => {
			moment.locale('fa')
			const date = moment().subtract(1, 'day')
			const relative = getRelativeTime(date)
			
			expect(relative).toContain('پیش')
		})
	})

	describe('Persian Calendar Data', () => {
		it('should return correct Persian day names', () => {
			const dayNames = getPersianDayNames()
			expect(dayNames).toHaveLength(7)
			expect(dayNames[0]).toBe('یکشنبه') // Sunday
			expect(dayNames[6]).toBe('شنبه')   // Saturday
		})

		it('should return correct Persian month names', () => {
			const monthNames = getPersianMonthNames()
			expect(monthNames).toHaveLength(12)
			expect(monthNames[0]).toBe('فروردین') // Farvardin
			expect(monthNames[11]).toBe('اسفند')  // Esfand
		})

		it('should return correct Persian short day names', () => {
			const shortDayNames = getPersianShortDayNames()
			expect(shortDayNames).toHaveLength(7)
			expect(shortDayNames[0]).toBe('ی') // Yek
			expect(shortDayNames[6]).toBe('ش') // Shan
		})

		it('should return correct Persian short month names', () => {
			const shortMonthNames = getPersianShortMonthNames()
			expect(shortMonthNames).toHaveLength(12)
			expect(shortMonthNames[0]).toBe('فر') // Far
			expect(shortMonthNames[11]).toBe('اس') // Esf
		})

		it('should return correct Persian first day of week', () => {
			expect(getPersianFirstDayOfWeek()).toBe(6) // Saturday
		})
	})

	describe('Jalali Date Operations', () => {
		it('should check if date is Jalali today', () => {
			const today = moment().jMoment()
			expect(isJalaliToday(today.toDate())).toBe(true)
		})

		it('should check if date is Jalali tomorrow', () => {
			const tomorrow = moment().add(1, 'day').jMoment()
			expect(isJalaliTomorrow(tomorrow.toDate())).toBe(true)
		})

		it('should get Jalali components', () => {
			const date = new Date('2024-01-15')
			const components = getJalaliComponents(date)
			
			expect(components).toBeDefined()
			expect(components.year).toBe(1402)
			expect(components.month).toBe(11) // 1-based month
			expect(components.day).toBe(25)
		})

		it('should create Jalali date from components', () => {
			const jalaliDate = createJalaliDate(1402, 10, 25)
			
			expect(jalaliDate).toBeDefined()
			expect(jalaliDate.jYear()).toBe(1402)
			expect(jalaliDate.jMonth()).toBe(9) // 0-based month
			expect(jalaliDate.jDate()).toBe(25)
		})

		it('should get current Jalali date', () => {
			const currentJalali = getCurrentJalaliDate()
			
			expect(currentJalali).toBeDefined()
			expect(currentJalali.jYear()).toBeGreaterThan(1400)
		})
	})

	describe('Jalali Date Formatting', () => {
		it('should format Jalali date with default format', () => {
			const date = new Date('2024-01-15')
			const formatted = formatJalaliDate(date)
			
			expect(formatted).toContain('1402')
		})

		it('should format Jalali date with custom format', () => {
			const date = new Date('2024-01-15')
			const formatted = formatJalaliDate(date, 'jYYYY/jM/jD')
			
			expect(formatted).toMatch(/^\d{4}\/\d{1,2}\/\d{1,2}$/)
		})

		it('should get readable Jalali date', () => {
			const date = new Date('2024-01-15')
			const readable = getReadableJalaliDate(date)
			
			expect(readable).toBeDefined()
			expect(typeof readable).toBe('string')
		})
	})
})
