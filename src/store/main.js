/**
 * SPDX-FileCopyrightText: 2018 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import 'url-search-params-polyfill'

import { loadState } from '@nextcloud/initial-state'
import Vue from 'vue'
import Vuex from 'vuex'
import axios from '@nextcloud/axios'
import { generateOcsUrl } from '@nextcloud/router'
import { useBoardStore } from '../stores/board.js'
Vue.use(Vuex)

const debug = process.env.NODE_ENV !== 'production'

export const BOARD_FILTERS = {
	ALL: '',
	ARCHIVED: 'archived',
	SHARED: 'shared',
}

/**
 *
 */
export default function storeFactory() {
	return new Vuex.Store({
		strict: debug,
		state: {
			isFullApp: true,
			config: loadState('deck', 'config', {}),
			navShown: localStorage.getItem('deck.navShown') === null || localStorage.getItem('deck.navShown') === 'true',
			compactMode: localStorage.getItem('deck.compactMode') === 'true',
			showCardCover: localStorage.getItem('deck.showCardCover') === 'true',
			sidebarShown: false,
			currentCard: null,
			hasCardSaveError: false,
			sharees: [],
			searchQuery: '',
			activity: [],
			activityLoadMore: true,
			shortcutLock: false,
		},
		getters: {
			config: state => (key) => {
				if (!state.isFullApp && key === 'cardDetailsInModal') {
					return true
				}

				return state.config[key]
			},
			getSearchQuery: state => {
				return state.searchQuery
			},
		},
		mutations: {
			setFullApp(state, isFullApp) {
				Vue.set(state, 'isFullApp', isFullApp)
			},
			setHasCardSaveError(state, hasCardSaveError) {
				Vue.set(state, 'hasCardSaveError', hasCardSaveError)
			},
			SET_CONFIG(state, { key, value }) {
				const [scope, id, configKey] = key.split(':', 3)
				let indexExisting = -1
				switch (scope) {
				case 'board':
					indexExisting = useBoardStore().boards.findIndex((b) => {
						return id === '' + b.id
					})

					if (indexExisting > -1) {
						Vue.set(useBoardStore().boards[indexExisting].settings, configKey, value)
					}
					break
				default:
					Vue.set(state.config, key, value)
				}
			},
			setSearchQuery(state, searchQuery) {
				state.searchQuery = searchQuery
			},
			toggleNav(state, navState) {
				state.navShown = navState
				localStorage.setItem('deck.navShown', navState)
			},
			toggleSidebar(state) {
				state.sidebarShown = !state.sidebarShown
			},
			toggleCompactMode(state) {
				state.compactMode = !state.compactMode
				localStorage.setItem('deck.compactMode', state.compactMode)
			},
			toggleShowCardCover(state) {
				state.showCardCover = !state.showCardCover
				localStorage.setItem('deck.showCardCover', state.showCardCover)
			},
			setSharees(state, shareesUsersAndGroups) {
				Vue.set(state, 'sharees', shareesUsersAndGroups)
			},
			setCurrentCard(state, card) {
				state.currentCard = card
			},
			TOGGLE_SHORTCUT_LOCK(state, lock) {
				state.shortcutLock = lock
			},
		},
		actions: {
			setFullApp({ commit }, isFullApp) {
				commit('setFullApp', isFullApp)
			},
			async setConfig({ commit }, config) {
				for (const key in config) {
					try {
						await axios.post(generateOcsUrl(`apps/deck/api/v1.0/config/${key}`), {
							value: config[key],
						})
						commit('SET_CONFIG', { key, value: config[key] })
					} catch (e) {
						console.error(`Error while saving ${key}`, e.response)
						throw e
					}
				}
			},
			async loadSharees({ commit }, query) {
				if (typeof query === 'undefined') {
					return
				}
				const params = {
					search: query,
					itemType: 'deck',
					shareTypes: [0, 1, 6, 7],
					limit: 200,
				}

				const response = await axios.get(generateOcsUrl('/core/autocomplete/get'), { params })
				commit('setSharees', response.data.ocs.data)
			},

			toggleNav({ commit }, navState) {
				commit('toggleNav', navState)
			},
			toggleSidebar({ commit }) {
				commit('toggleSidebar')
			},
			toggleCompactMode({ commit }) {
				commit('toggleCompactMode')
			},
			toggleShowCardCover({ commit }) {
				commit('toggleShowCardCover')
			},
			setCurrentCard({ commit }, card) {
				commit('setCurrentCard', card)
			},

			toggleShortcutLock({ commit }, lock) {
				commit('TOGGLE_SHORTCUT_LOCK', lock)
			},
		},
	})
}
