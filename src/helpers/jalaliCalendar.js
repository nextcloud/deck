/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import moment from '@nextcloud/moment'
import 'moment-jalaali'

/**
 * Jalali Calendar Utility for Persian/Shamsi calendar support
 * Provides conversion between Gregorian and Jalali dates
 */

/**
 * Check if the current locale is Persian/Farsi
 * @returns {boolean}
 */
export function isPersianLocale() {
	const locale = moment.locale()
	return locale === 'fa' || locale === 'fa-ir' || locale === 'persian'
}

/**
 * Get the current calendar type based on locale
 * @returns {string} 'jalali' or 'gregorian'
 */
export function getCurrentCalendarType() {
	return isPersianLocale() ? 'jalali' : 'gregorian'
}

/**
 * Convert a Gregorian date to Jalali
 * @param {Date|string|moment} date - The date to convert
 * @returns {moment} Jalali date as moment object
 */
export function toJalali(date) {
	if (!date) return null
	const momentDate = moment(date)
	return momentDate.jMoment()
}

/**
 * Convert a Jalali date to Gregorian
 * @param {Date|string|moment} jalaliDate - The Jalali date to convert
 * @returns {moment} Gregorian date as moment object
 */
export function toGregorian(jalaliDate) {
	if (!jalaliDate) return null
	const momentDate = moment(jalaliDate)
	return momentDate.jMoment().toMoment()
}

/**
 * Format a date according to the current calendar type
 * @param {Date|string|moment} date - The date to format
 * @param {string} format - The format string
 * @returns {string} Formatted date string
 */
export function formatDate(date, format = 'LLL') {
	if (!date) return ''
	
	const calendarType = getCurrentCalendarType()
	
	if (calendarType === 'jalali') {
		const jalaliDate = toJalali(date)
		return jalaliDate.format(format)
	}
	
	return moment(date).format(format)
}

/**
 * Get relative time (e.g., "2 hours ago") in the current calendar
 * @param {Date|string|moment} date - The date to get relative time for
 * @returns {string} Relative time string
 */
export function getRelativeTime(date) {
	if (!date) return ''
	
	const calendarType = getCurrentCalendarType()
	
	if (calendarType === 'jalali') {
		const jalaliDate = toJalali(date)
		return jalaliDate.fromNow()
	}
	
	return moment(date).fromNow()
}

/**
 * Get day names in Persian for Jalali calendar
 * @returns {Array} Array of Persian day names
 */
export function getPersianDayNames() {
	return [
		'یکشنبه',    // Sunday
		'دوشنبه',    // Monday
		'سه‌شنبه',   // Tuesday
		'چهارشنبه',  // Wednesday
		'پنج‌شنبه',  // Thursday
		'جمعه',      // Friday
		'شنبه'       // Saturday
	]
}

/**
 * Get month names in Persian for Jalali calendar
 * @returns {Array} Array of Persian month names
 */
export function getPersianMonthNames() {
	return [
		'فروردین',   // Farvardin
		'اردیبهشت',  // Ordibehesht
		'خرداد',     // Khordad
		'تیر',       // Tir
		'مرداد',     // Mordad
		'شهریور',    // Shahrivar
		'مهر',       // Mehr
		'آبان',      // Aban
		'آذر',       // Azar
		'دی',        // Dey
		'بهمن',      // Bahman
		'اسفند'      // Esfand
	]
}

/**
 * Get short month names in Persian for Jalali calendar
 * @returns {Array} Array of short Persian month names
 */
export function getPersianShortMonthNames() {
	return [
		'فر',        // Far
		'ارد',       // Ord
		'خر',        // Kho
		'تی',        // Tir
		'مر',        // Mor
		'شه',        // Sha
		'مه',        // Meh
		'آبا',       // Aba
		'آذ',        // Aza
		'دی',        // Dey
		'به',        // Bah
		'اس'         // Esf
	]
}

/**
 * Get short day names in Persian for Jalali calendar
 * @returns {Array} Array of short Persian day names
 */
export function getPersianShortDayNames() {
	return [
		'ی',         // Yek (Sunday)
		'د',         // Do (Monday)
		'س',         // Se (Tuesday)
		'چ',         // Chah (Wednesday)
		'پ',         // Pan (Thursday)
		'ج',         // Jom (Friday)
		'ش'          // Shan (Saturday)
	]
}

/**
 * Get the first day of week for Persian calendar (Saturday = 6)
 * @returns {number} First day of week (0-6, where 0 is Sunday)
 */
export function getPersianFirstDayOfWeek() {
	return 6 // Saturday is the first day of week in Persian calendar
}

/**
 * Check if a date is today in Jalali calendar
 * @param {Date|string|moment} date - The date to check
 * @returns {boolean} True if the date is today
 */
export function isJalaliToday(date) {
	if (!date) return false
	const today = moment().jMoment()
	const checkDate = moment(date).jMoment()
	return today.isSame(checkDate, 'day')
}

/**
 * Check if a date is tomorrow in Jalali calendar
 * @param {Date|string|moment} date - The date to check
 * @returns {boolean} True if the date is tomorrow
 */
export function isJalaliTomorrow(date) {
	if (!date) return false
	const tomorrow = moment().add(1, 'day').jMoment()
	const checkDate = moment(date).jMoment()
	return tomorrow.isSame(checkDate, 'day')
}

/**
 * Get Jalali date components
 * @param {Date|string|moment} date - The date to get components for
 * @returns {Object} Object with jalali year, month, and day
 */
export function getJalaliComponents(date) {
	if (!date) return null
	const jalaliDate = toJalali(date)
	return {
		year: jalaliDate.jYear(),
		month: jalaliDate.jMonth() + 1, // moment-jalaali months are 0-based
		day: jalaliDate.jDate()
	}
}

/**
 * Create a Jalali date from components
 * @param {number} year - Jalali year
 * @param {number} month - Jalali month (1-12)
 * @param {number} day - Jalali day (1-31)
 * @returns {moment} Jalali date as moment object
 */
export function createJalaliDate(year, month, day) {
	return moment.jMoment(`${year}/${month}/${day}`, 'jYYYY/jM/jD')
}

/**
 * Get the current Jalali date
 * @returns {moment} Current Jalali date
 */
export function getCurrentJalaliDate() {
	return moment().jMoment()
}

/**
 * Format a Jalali date for display
 * @param {Date|string|moment} date - The date to format
 * @param {string} format - The format string (default: Persian long format)
 * @returns {string} Formatted Jalali date string
 */
export function formatJalaliDate(date, format = 'jYYYY jMMMM jD') {
	if (!date) return ''
	const jalaliDate = toJalali(date)
	return jalaliDate.format(format)
}

/**
 * Get a human-readable Jalali date string
 * @param {Date|string|moment} date - The date to format
 * @returns {string} Human-readable Jalali date string
 */
export function getReadableJalaliDate(date) {
	if (!date) return ''
	
	const jalaliDate = toJalali(date)
	const today = moment().jMoment()
	const tomorrow = moment().add(1, 'day').jMoment()
	
	if (jalaliDate.isSame(today, 'day')) {
		return 'امروز'
	} else if (jalaliDate.isSame(tomorrow, 'day')) {
		return 'فردا'
	} else if (jalaliDate.isBefore(today, 'day')) {
		return jalaliDate.fromNow()
	} else {
		return jalaliDate.fromNow()
	}
}
