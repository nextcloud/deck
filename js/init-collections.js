/*
 * @copyright Copyright (c) 2019 Julius Härtl <jus@bitgrid.net>
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

'use strict';

/* global __webpack_nonce__ __webpack_public_path__ OC t n */
__webpack_nonce__ = btoa(OC.requestToken); // eslint-disable-line no-native-reassign
__webpack_public_path__ = OC.linkTo('deck', 'js/build/');

import Vue from 'vue';
import BoardSelector from './views/BoardSelector';

import './../css/collections.css';

((function(OCP) {

	Vue.prototype.$ = $
	Vue.prototype.t = t
	Vue.prototype.n = n
	Vue.prototype.OC = OC

	OCP.Collaboration.registerType('deck', {
		action: () => {
			return new Promise((resolve, reject) => {
				const container = document.createElement('div');
				container.id = 'deck-board-select';
				const body = document.getElementById('body-user');
				body.append(container);
				const ComponentVM = new Vue({
					render: h => h(BoardSelector),
				});
				ComponentVM.$mount(container);
				ComponentVM.$root.$on('close', () => {
					ComponentVM.$el.remove();
					ComponentVM.$destroy();
					reject();
				});
				ComponentVM.$root.$on('select', (id) => {
					resolve(id);
					ComponentVM.$el.remove();
					ComponentVM.$destroy();
				});
			});
		},
		typeString: t('deck', 'board'),
		typeIconClass: 'icon-deck'
	});
})(window.OCP));
