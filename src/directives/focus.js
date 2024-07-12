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
	bind(el, binding, vnode) {
		// When the component of the element gets activated
		vnode.context.$on('hook:activated', () => focusElement(el, binding))
	},
	// When the bound element is inserted into the DOM...
	inserted: focusElement,
}
