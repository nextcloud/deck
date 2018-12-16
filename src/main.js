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
import { translate, translatePlural } from 'nextcloud-server/dist/l10n'
import { generateFilePath } from 'nextcloud-server/dist/router'
import VTooltip from 'v-tooltip'

/**
 * Board model
 *
 * @typedef {Object} Board
 * @property {String} title
 * @property {boolean} archived
 */

// eslint-disable-next-line
__webpack_nonce__ = btoa(OC.requestToken)
// eslint-disable-next-line
__webpack_public_path__ = generateFilePath('deck', '', 'js/')

sync(store, router)

Vue.mixin({
	methods: {
		t: translate,
		n: translatePlural
	}
})

Vue.use(VTooltip)

/* eslint-disable-next-line no-new */
new Vue({
	el: '#content',
	router,
	store,
	render: h => h(App)
})
