/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import Vue from 'vue'
import axios from '@nextcloud/axios'
import { defineStore } from 'pinia'
import { generateOcsUrl } from '@nextcloud/router'
import { loadState } from '@nextcloud/initial-state'
import { useBoardStore } from './board.js'

export const useSettingsStore = defineStore('settings', {
	state: () => ({
		isFullApp: true,
		navShown: localStorage.getItem('deck.navShown') === null || localStorage.getItem('deck.navShown') === 'true',
		compactMode: localStorage.getItem('deck.compactMode') === 'true',
		showCardCover: localStorage.getItem('deck.showCardCover') === 'true',
		hasCardSaveError: false,
		sharees: [],
		searchQuery: '',
		shortcutLock: false,
		config: loadState('deck', 'config', {}),
	}),
	getters: {
		configByKey: (state) => (key) => {
			if (!state.isFullApp && key === 'cardDetailsInModal') {
				return true
			}

			return state.config[key]
		},
	},
	actions: {
		setFullApp(isFullApp) {
			this.isFullApp = isFullApp
		},
		setHasCardSaveError(hasCardSaveError) {
			this.hasCardSaveError = hasCardSaveError
		},
		setSearchQuery(searchQuery) {
			this.searchQuery = searchQuery
		},
		toggleNav(navState) {
			this.navShown = navState
			localStorage.setItem('deck.navShown', navState)
		},
		toggleCompactMode() {
			this.compactMode = !this.compactMode
			localStorage.setItem('deck.compactMode', this.compactMode)
		},
		toggleShowCardCover() {
			this.showCardCover = !this.showCardCover
			localStorage.setItem('deck.showCardCover', this.showCardCover)
		},
		setSharees(shareesUsersAndGroups) {
			this.sharees = shareesUsersAndGroups
		},
		setConfigLocal({ key, value }) {
			const [scope, id, configKey] = key.split(':', 3)
			switch (scope) {
			case 'board': {
				const boardStore = useBoardStore()
				const indexExisting = boardStore.boards.findIndex((b) => {
					return id === '' + b.id
				})

				if (indexExisting > -1) {
					if (!boardStore.boards[indexExisting].settings) {
						Vue.set(boardStore.boards[indexExisting], 'settings', {})
					}
					Vue.set(boardStore.boards[indexExisting].settings, configKey, value)
				}
				break
			}
			default:
				Vue.set(this.config, key, value)
			}
		},
		async setConfig(config) {
			for (const [key, value] of Object.entries(config)) {
				try {
					await axios.post(generateOcsUrl(`apps/deck/api/v1.0/config/${key}`), {
						value,
					})
					this.setConfigLocal({ key, value })
				} catch (e) {
					console.error(`Error while saving ${key}`, e.response)
					throw e
				}
			}
		},
		toggleShortcutLock(lock) {
			this.shortcutLock = lock
		},
		async loadSharees(query) {
			if (typeof query === 'undefined') {
				return
			}
			const params = {
				search: query,
				itemType: 'deck',
				shareTypes: [0, 1, 6, 7],
				limit: 20,
			}

			const response = await axios.get(generateOcsUrl('/core/autocomplete/get'), { params })
			this.setSharees(response.data.ocs.data)
		},
	},
})
