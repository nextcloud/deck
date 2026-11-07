/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

// Card dates are stored in a DATETIME column, so anything outside of this
// range cannot be read back again. Typing a year like 20250 into a native
// date input would otherwise leave the card unreadable.
export const MIN_DATE = new Date('1000-01-01T00:00:00')
export const MAX_DATE = new Date('9999-12-31T23:59:59')
