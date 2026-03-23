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

function registerActivationHook(el, binding, vnode) {
	const instance = binding.instance ?? vnode?.context
	instance?.$on?.('hook:activated', () => focusElement(el, binding))
}

// Register a global custom directive called `v-focus`
export default {
	bind(el, binding, vnode) {
		// When the component of the element gets activated
		registerActivationHook(el, binding, vnode)
	},
	beforeMount(el, binding, vnode) {
		registerActivationHook(el, binding, vnode)
	},
	// When the bound element is inserted into the DOM...
	inserted: focusElement,
	mounted: focusElement,
}
