/*
 * @copyright Copyright (c) 2018 Julius Härtl <jus@bitgrid.net>
 *
 * @author Julius Härtl <jus@bitgrid.net>
 *
 * @license GNU AGPL version 3 or any later version
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 *
 */
import Vue from 'vue'
import App from './App'
import router from './router'
import store from './store/main'
import { sync } from 'vuex-router-sync'
import { translate, translatePlural } from '@nextcloud/l10n'
import { generateFilePath } from '@nextcloud/router'
import { showError } from '@nextcloud/dialogs'
import { subscribe } from '@nextcloud/event-bus'
import { Tooltip } from '@nextcloud/vue'
import ClickOutside from 'vue-click-outside'
import './models'

// the server snap.js conflicts with vertical scrolling so we disable it
document.body.setAttribute('data-snap-ignore', 'true')

// eslint-disable-next-line
__webpack_nonce__ = btoa(OC.requestToken)
if (!process.env.HOT) {
	// eslint-disable-next-line
	__webpack_public_path__ = generateFilePath('deck', '', 'js/')
}
sync(store, router)

Vue.prototype.t = translate
Vue.prototype.n = translatePlural

Vue.directive('tooltip', Tooltip)
Vue.directive('click-outside', ClickOutside)

Vue.directive('focus', {
	inserted(el) {
		el.focus()
	},
})

Vue.config.errorHandler = (err, vm, info) => {
	if (err.response && err.response.data.message) {
		const errorMessage = t('deck', 'Something went wrong')
		showError(`${errorMessage}: ${err.response.data.status} ${err.response.data.message}`)
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
	Object.assign(window.OCA.Files, { App: { fileList: { filesClient: OC.Files.getClient() } } }, window.OCA.Files)
})

/* eslint-disable-next-line no-new */
new Vue({
	el: '#content',
	// eslint-disable-next-line vue/match-component-file-name
	name: 'Deck',
	router,
	store,
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

		// FIXME remove this once Nextcloud 20 is minimum required version
		// eslint-disable-next-line
		new OCA.Search(this.filter, this.cleanSearch)

		this.interval = setInterval(() => {
			this.time = Date.now()
		}, 1000)
	},
	beforeDestroy() {
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
	render: h => h(App),
})

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
