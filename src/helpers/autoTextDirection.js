/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

// Editable fields whose direction should follow what the user types. Only
// free-text fields are included; typed inputs such as number/date/email keep
// their native direction.
const EDITABLE_SELECTOR = [
	'input:not([type])',
	'input[type="text"]',
	'input[type="search"]',
	'textarea',
	'[contenteditable=""]',
	'[contenteditable="true"]',
	'[contenteditable="plaintext-only"]',
].join(',')

// Read-only elements that display user-generated text and should follow the
// content's direction (e.g. a Persian card title shown in an English UI).
const DISPLAY_SELECTOR = [
	'.app-sidebar-header__mainname', // card/board title in the sidebar header
	'.app-sidebar-header__subname', // sidebar subtitle
	'.comment--content', // rendered comment body
].join(',')

const SELECTOR = `${EDITABLE_SELECTOR},${DISPLAY_SELECTOR}`

/**
 * Give an element `dir="auto"` so the browser derives its base direction from
 * the content: RTL for text that starts with a Persian/Arabic (first-strong)
 * character, LTR otherwise. The attribute is live, so the direction keeps
 * updating as the content changes, and `text-align: start` — re-asserted for
 * the RTL case in css/fonts.scss — puts RTL text on the right.
 *
 * Elements that already declare an explicit `dir` are left untouched.
 *
 * @param {Element} el the candidate element
 */
function applyAutoDir(el) {
	if (el instanceof HTMLElement && !el.hasAttribute('dir')) {
		el.setAttribute('dir', 'auto')
	}
}

/**
 * Apply auto-direction to a node and any matching descendants.
 *
 * @param {Node} root the inserted node (or the document root for the first pass)
 */
function processTree(root) {
	if (!(root instanceof HTMLElement)) {
		return
	}
	if (root.matches(SELECTOR)) {
		applyAutoDir(root)
	}
	root.querySelectorAll(SELECTOR).forEach(applyAutoDir)
}

// First pass over whatever is already in the DOM.
processTree(document.documentElement)

// Vue and the Nextcloud components render most fields and content lazily, so
// watch for nodes added later and tag them as they appear.
const observer = new MutationObserver(mutations => {
	for (const mutation of mutations) {
		for (const node of mutation.addedNodes) {
			processTree(node)
		}
	}
})
observer.observe(document.documentElement, { childList: true, subtree: true })
