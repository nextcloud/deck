/**
 * SPDX-FileCopyrightText: 2018 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
import { createApp } from 'vue'
import App from './App.vue'
import router from './router.js'
import store from './store/main.js'
import { sync } from 'vuex-router-sync'
import { translate, translatePlural } from '@nextcloud/l10n'
import { showError } from '@nextcloud/dialogs'
import { subscribe } from '@nextcloud/event-bus'
import ClickOutside from 'vue-click-outside'
import './shared-init.js'
import './models/index.js'
import './sessions.js'

// the server snap.js conflicts with vertical scrolling so we disable it
document.body.setAttribute('data-snap-ignore', 'true')

sync(store, router)

const app = createApp(App)

app.config.globalProperties.t = translate
app.config.globalProperties.n = translatePlural

app.directive('click-outside', ClickOutside)

app.directive('focus', {
	mounted(el) {
		el.focus()
	},
})

app.config.errorHandler = (err, vm, info) => {
	if (err.response && err.response.data.message) {
		const errorMessage = translate('deck', 'Something went wrong')
		showError(
			`${errorMessage}: ${err.response.data.status} ${err.response.data.message}`,
		)
	}
	console.error(err)
}

// TODO: remove when we have a proper fileinfo standalone library
// original scripts are loaded from
// https://github.com/nextcloud/server/blob/5bf3d1bb384da56adbf205752be8f840aac3b0c5/lib/private/legacy/template.php#L120-L122
window.addEventListener('DOMContentLoaded', () => {
	if (!window.OCA.Files) {
		window.OCA.Files = {}
	}
	// register unused client for the sidebar to have access to its parser methods
	Object.assign(
		window.OCA.Files,
		{ App: { fileList: { filesClient: OC.Files.getClient() } } },
		window.OCA.Files,
	)
})

app.mixin({
	data() {
		return {
			time: Date.now(),
			interval: null,
		}
	},
	created() {
		subscribe('nextcloud:unified-search.search', ({ query }) => {
			this.$store.commit('setSearchQuery', query)
		})
		subscribe('nextcloud:unified-search.reset', () => {
			this.$store.commit('setSearchQuery', '')
		})

		this.interval = setInterval(() => {
			this.time = Date.now()
		}, 1000)
	},
	beforeUnmount() {
		clearInterval(this.interval)
	},
	methods: {
		filter(query) {
			this.$store.commit('setSearchQuery', query)
		},
		cleanSearch() {
			this.$store.commit('setSearchQuery', '')
		},
	},
})

app.use(router)
app.use(store)
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
	store.dispatch('addCardAction', cardAction)
}
