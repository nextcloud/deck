/*
 * @copyright Copyright (c) 2018 Michael Weimann <mail@michael-weimann.eu>
 *
 * @author Michael Weimann <mail@michael-weimann.eu>
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

import { translate as t } from 'nextcloud-server/dist/l10n'
import { mapBoardToItem } from './boards'
import store from '../main'

let defaultCategories = [
	{
		id: 'deck-boards',
		classes: [],
		icon: 'icon-deck',
		text: t('deck', 'All boards'),
		router: {
			name: 'boards'
		}
	},
	{
		id: 'deck-boards-archived',
		classes: [],
		icon: 'icon-archive',
		text: t('deck', 'Archived boards'),
		router: {
			name: 'boards.archived'
		}
	},
	{
		id: 'deck-boards-shared',
		classes: [],
		icon: 'icon-shared',
		text: t('deck', 'Shared boards'),
		router: {
			name: 'boards.shared'
		}
	}
]

const state = {
	hidden: false,
	loading: false,
	addButton: {
		icon: 'icon-add',
		classes: [],
		text: t('deck', 'Create new board'),
		edit: {
			text: t('deck', 'new board'),
			action: () => {
			},
			reset: () => {
			}
		},
		action: () => {
			store.dispatch('nav/startAddBoard')
		}
	}
}

const getters = {
	menu: (state, getters, rootState) => {
		return {
			loading: state.loading,
			items: defaultCategories
				.concat(rootState.boards.boards.map(mapBoardToItem))
				.concat([state.addButton])
		}
	}
}

const actions = {
	toggle({ commit }) {
		commit('toggle')
	},
	startAddBoard({ commit }) {
		commit('startAddBoard')
	}
}

// mutations
const mutations = {
	toggle(state) {
		state.hidden = !state.hidden
	},
	startAddBoard(state) {
		state.addButton.classes.push('editing')
	}
}

export default {
	namespaced: true,
	state,
	getters,
	actions,
	mutations
}
