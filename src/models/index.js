/**
 * SPDX-FileCopyrightText: 2018 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

/**
 * Board model
 *
 * @typedef {object} Board
 * @property {string} title
 * @property {boolean} archived
 * @property {number} shared 1 (shared) or 0 (not shared)
 */

/**
 * Stack model
 *
 * @typedef {object} Stack
 * @property {string} title
 * @property {number} boardId
 * @property {number} order
 */

/**
 * Card model
 *
 * @typedef {object} Card
 * @property {string} title
 * @property {boolean} archived
 * @property {number} order
 */
/**
 * Label model
 *
 * @typedef {object} Label
 * @property {string} title
 * @property {string} color
 */
