/**
 * SPDX-FileCopyrightText: 2021 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

export default {
	state: {
		actions: {
			card: [],
		},
	},
	getters: {
		cardActions: (state) => state.actions.card,
	},
	mutations: {
		ADD_CARD_ACTION(state, action) {
			state.actions.card.push(action)
		},
	},
	actions: {
		async addCardAction({ commit }, action) {
			commit('ADD_CARD_ACTION', action)
		},
	},
}
