/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { defineCustomElement } from 'vue'
import BoardElement from './BoardElement.vue'

export { parseId } from './BoardElement.vue'

export const BOARD_ELEMENT_NAME = 'nextcloud-deck-board'

export function registerBoardElement(mountBoard) {
	if (customElements.get(BOARD_ELEMENT_NAME)) {
		return
	}

	customElements.define(BOARD_ELEMENT_NAME, defineCustomElement(BoardElement, {
		shadowRoot: false,
		configureApp(app) {
			app.provide('mountDeckBoard', mountBoard)
		},
	}))
}
