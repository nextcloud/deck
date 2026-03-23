/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

const HANDLER_KEY = '__deckClickOutsideHandler__'
const EVENTS = window.PointerEvent ? ['pointerdown'] : ['click', 'touchstart']

function isOutsideClick(el, event) {
	const target = event.target
	return target instanceof Node && !el.contains(target)
}

function bindHandler(el, binding) {
	if (typeof binding.value !== 'function') {
		return
	}

	const handler = (event) => {
		if (!isOutsideClick(el, event)) {
			return
		}

		binding.value(event)
	}

	el[HANDLER_KEY] = handler
	EVENTS.forEach((eventName) => {
		document.addEventListener(eventName, handler, true)
	})
}

function unbindHandler(el) {
	const handler = el[HANDLER_KEY]
	if (!handler) {
		return
	}

	EVENTS.forEach((eventName) => {
		document.removeEventListener(eventName, handler, true)
	})
	delete el[HANDLER_KEY]
}

export default {
	bind(el, binding) {
		bindHandler(el, binding)
	},
	update(el, binding) {
		if (binding.value === binding.oldValue) {
			return
		}

		unbindHandler(el)
		bindHandler(el, binding)
	},
	unbind(el) {
		unbindHandler(el)
	},
}
