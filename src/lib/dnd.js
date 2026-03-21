/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { Container, Draggable } from 'vue-smooth-dnd'

const transientBodyClasses = [
	'smooth-dnd-no-user-select',
	'smooth-dnd-disable-touch-action',
]

export const DeckDragContainer = Container
export const DeckDraggable = Draggable

export function resetDeckDndDocumentState(target = document.body) {
	if (!target?.classList) {
		return
	}

	transientBodyClasses.forEach((className) => {
		target.classList.remove(className)
	})
}