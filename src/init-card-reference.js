/**
 * @copyright Copyright (c) 2022 Julien Veyssier <eneiluj@posteo.net>
 *
 * @author Julien Veyssier <eneiluj@posteo.net>
 *
 * @license AGPL-3.0-or-later
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 */

import { registerWidget } from '@nextcloud/vue-richtext'
import { Tooltip } from '@nextcloud/vue'
import Vue from 'vue'
import CardReferenceWidget from './views/CardReferenceWidget.vue'

import { translate, translatePlural } from '@nextcloud/l10n'

Vue.prototype.t = translate
Vue.prototype.n = translatePlural
Vue.prototype.OC = window.OC
Vue.prototype.OCA = window.OCA
Vue.directive('tooltip', Tooltip)

registerWidget('deck-card', (el, { richObjectType, richObject, accessible }) => {
	// trick to change the wrapper element size, otherwise it always is 100%
	// which is not very nice with a simple card
	el.parentNode.style['max-width'] = '400px'
	el.parentNode.style['margin-left'] = '0'
	el.parentNode.style['margin-right'] = '0'

	const Widget = Vue.extend(CardReferenceWidget)
	new Widget({
		propsData: {
			richObjectType,
			richObject,
			accessible,
		},
	}).$mount(el)
})
