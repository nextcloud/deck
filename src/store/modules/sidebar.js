/*
 * @copyright Copyright (c) 2018 Julius Härtl <jus@bitgrid.net>
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

// initial state
const state = {
	hidden: true,
	component: 'Sidebar'
}

// getters
const getters = {}

// actions
const actions = {
	toggle({ commit }) {
		commit('toggle')
	}
}

// mutations
const mutations = {
	toggle(state) {
		state.hidden = !state.hidden
	}
}

export default {
	namespaced: true,
	state,
	getters,
	actions,
	mutations
}
