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

const prepareVue = (Vue) => {
	Vue.prototype.t = translate
	Vue.prototype.n = translatePlural
	Vue.prototype.OC = window.OC
	Vue.prototype.OCA = window.OCA
	Vue.directive('focus', {
		inserted(el) {
			el.focus()
		},
	})
}

registerWidget('deck-card', async (el, { richObjectType, richObject, accessible }) => {
	const { default: Vue } = await import('vue')
	prepareVue(Vue)
	const { default: CardReferenceWidget } = await import('./views/CardReferenceWidget.vue')
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

registerWidget('deck-board', async (el, { richObjectType, richObject, accessible }) => {
	const { default: Vue } = await import('vue')
	prepareVue(Vue)
	const { default: BoardReferenceWidget } = await import('./views/BoardReferenceWidget.vue')
	el.parentNode.style['max-width'] = '400px'
	el.parentNode.style['margin-left'] = '0'
	el.parentNode.style['margin-right'] = '0'

	const Widget = Vue.extend(BoardReferenceWidget)
	new Widget({
		propsData: {
			richObjectType,
			richObject,
			accessible,
		},
	}).$mount(el)
})

registerWidget('deck-comment', async (el, { richObjectType, richObject, accessible }) => {
	const { default: Vue } = await import('vue')
	prepareVue(Vue)
	const { default: CommentReferenceWidget } = await import('./views/CommentReferenceWidget.vue')

	el.parentNode.style['max-width'] = '400px'
	el.parentNode.style['margin-left'] = '0'
	el.parentNode.style['margin-right'] = '0'

	const Widget = Vue.extend(CommentReferenceWidget)
	new Widget({
		propsData: {
			richObjectType,
			richObject,
			accessible,
		},
	}).$mount(el)
})

registerCustomPickerElement('create-new-deck-card', async (el, { providerId, accessible }) => {
	const { default: Vue } = await import('vue')
	Vue.mixin({ methods: { t, n } })
	const { default: CreateNewCardCustomPicker } = await import('./views/CreateNewCardCustomPicker.vue')
	const Element = Vue.extend(CreateNewCardCustomPicker)
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
