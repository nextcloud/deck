/**
 * SPDX-FileCopyrightText: 2018 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
import { createApp } from 'vue'
import App from './App.vue'
import router from './router.js'
import { translate, translatePlural } from '@nextcloud/l10n'
import { showError } from '@nextcloud/dialogs'
import './shared-init.js'
import './models/index.js'
import { initSessions } from './sessions.js'
import { useActionsStore } from './stores/actions.js'
import { createPinia } from 'pinia'

// the server snap.js conflicts with vertical scrolling so we disable it
document.body.setAttribute('data-snap-ignore', 'true')

const app = createApp(App)

const pinia = createPinia()
app.use(router)
app.use(pinia)

app.config.globalProperties.t = translate
app.config.globalProperties.n = translatePlural

app.directive('focus', {
	inserted(el) {
		el.focus()
	},
})

app.config.errorHandler = (err, vm, info) => {
	if (err.response && err.response.data.message) {
		const errorMessage = t('deck', 'Something went wrong')
		showError(`${errorMessage}: ${err.response.data.status} ${err.response.data.message}`)
	}
	console.error(err)
}

initSessions()

app.mount('#content')

if (!window.OCA.Deck) {
	window.OCA.Deck = {}
}

/**
 * @typedef {object} CardRichObject
 * @property {string} id
 * @property {string} name
 * @property {string} boardname
 * @property {string} stackname
 * @property {string} link
 */

/**
 * @callback registerActionCallback
 * @param {CardRichObject} card
 */

/**
 * Frontend message API for adding actions to talk messages.
 *
 * @param {*} Object the wrapping object.
 * @param {string} label the action label.
 * @param {registerActionCallback} callback the callback function. This function will receive
 * the card as a parameter and be triggered by a click on the
 * action. The card parameter will be of the format of a rich object string
 * type "deck-card"
 * @param {string} icon the action label. E.g. "icon-reply"
 */
window.OCA.Deck.registerCardAction = ({ label, callback, icon }) => {
	const cardAction = {
		label,
		callback,
		icon,
	}
	const actionsStore = useActionsStore()
	actionsStore.addCardAction(cardAction)
}
