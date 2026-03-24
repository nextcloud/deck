/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
/**
 *
 * @param el
 * @param binding
 */
function focusElement(el, binding) {
	// If directive has bound value
	if (binding.value !== undefined && !binding.value) return

	// Focus the element
	el.focus()
}

// Register a global custom directive called `v-focus`
export default {
	mounted: focusElement,
}
