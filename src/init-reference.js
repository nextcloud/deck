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

import { registerWidget, registerCustomPickerElement, NcCustomPickerRenderResult } from '@nextcloud/vue/dist/Functions/registerReference.js'
import { translate, translatePlural } from '@nextcloud/l10n'

import './shared-init.js'

const prepareVue = async (Component = null) => {
	const { default: Vue } = await import('vue')
	const { default: ClickOutside } = await import('vue-click-outside')

	Vue.prototype.t = translate
	Vue.prototype.n = translatePlural
	Vue.prototype.OC = window.OC
	Vue.prototype.OCA = window.OCA
	Vue.directive('click-outside', ClickOutside)
	Vue.directive('focus', {
		inserted(el) {
			el.focus()
		},
	})
	if (!Component) {
		return Vue
	}

	return Vue.extend(Component)
}

registerWidget('deck-card', async (el, { richObjectType, richObject, accessible }) => {
	const { default: CardReferenceWidget } = await import('./views/CardReferenceWidget.vue')
	const Widget = await prepareVue(CardReferenceWidget)
	// trick to change the wrapper element size, otherwise it always is 100%
	// which is not very nice with a simple card
	el.parentNode.style['max-width'] = '400px'
	el.parentNode.style['margin-left'] = '0'
	el.parentNode.style['margin-right'] = '0'
	new Widget({
		propsData: {
			richObjectType,
			richObject,
			accessible,
		},
	}).$mount(el)
})

const boardWidgets = {}
registerWidget('deck-board', async (el, { richObjectType, richObject, accessible, interactive }) => {
	const { default: BoardReferenceWidget } = await import('./views/BoardReferenceWidget.vue')
	const Widget = await prepareVue(BoardReferenceWidget)
	boardWidgets[el] = new Widget({
		propsData: {
			richObjectType,
			richObject,
			accessible,
			interactive,
		},
	}).$mount(el)
}, (el) => {
	boardWidgets[el].$destroy()
	delete boardWidgets[el]
}, {
	fullWidth: true,
})

registerWidget('deck-comment', async (el, { richObjectType, richObject, accessible }) => {
	const { default: CommentReferenceWidget } = await import('./views/CommentReferenceWidget.vue')
	const Widget = await prepareVue(CommentReferenceWidget)

	el.parentNode.style['max-width'] = '400px'
	el.parentNode.style['margin-left'] = '0'
	el.parentNode.style['margin-right'] = '0'

	new Widget({
		propsData: {
			richObjectType,
			richObject,
			accessible,
		},
	}).$mount(el)
})

registerCustomPickerElement('create-new-deck-card', async (el, { providerId, accessible }) => {
	const { default: CreateNewCardCustomPicker } = await import('./views/CreateNewCardCustomPicker.vue')
	const Element = await prepareVue(CreateNewCardCustomPicker)
	const vueElement = new Element({
		propsData: {
			providerId,
			accessible,
		},
	}).$mount(el)
	return new NcCustomPickerRenderResult(vueElement.$el, vueElement)
}, (el, renderResult) => {
	renderResult.object.$destroy()
}, 'normal')
