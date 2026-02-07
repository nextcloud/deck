/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import Vue from 'vue'
import { StackAutomationApi } from '../services/StackAutomationApi.js'

const apiClient = new StackAutomationApi()

export default function stackAutomationModuleFactory() {
	return {
		state: {
			automations: {},
		},
		getters: {
			automationsByStack: state => (stackId) => {
				return state.automations[stackId] || []
			},
		},
		mutations: {
			setAutomations(state, { stackId, automations }) {
				Vue.set(state.automations, stackId, automations)
			},
			addAutomation(state, { stackId, automation }) {
				if (!state.automations[stackId]) {
					Vue.set(state.automations, stackId, [])
				}
				state.automations[stackId].push(automation)
			},
			updateAutomation(state, { stackId, automation }) {
				const automations = state.automations[stackId]
				if (automations) {
					const index = automations.findIndex(a => a.id === automation.id)
					if (index !== -1) {
						Vue.set(automations, index, automation)
					}
				}
			},
			deleteAutomation(state, { stackId, automationId }) {
				const automations = state.automations[stackId]
				if (automations) {
					const index = automations.findIndex(a => a.id === automationId)
					if (index !== -1) {
						automations.splice(index, 1)
					}
				}
			},
		},
		actions: {
			async loadStackAutomations({ commit }, stackId) {
				try {
					const automations = await apiClient.loadAutomations(stackId)
					commit('setAutomations', { stackId, automations })
					return automations
				} catch (error) {
					console.error('Failed to load automations', error)
					throw error
				}
			},
			async createStackAutomation({ commit }, { stackId, event, actionType, config, order }) {
				try {
					const automation = await apiClient.createAutomation(stackId, event, actionType, config, order)
					commit('addAutomation', { stackId, automation })
					return automation
				} catch (error) {
					console.error('Failed to create automation', error)
					throw error
				}
			},
			async updateStackAutomation({ commit }, { stackId, id, event, actionType, config, order }) {
				try {
					const automation = await apiClient.updateAutomation(id, event, actionType, config, order)
					commit('updateAutomation', { stackId, automation })
					return automation
				} catch (error) {
					console.error('Failed to update automation', error)
					throw error
				}
			},
			async deleteStackAutomation({ commit }, { stackId, id }) {
				try {
					await apiClient.deleteAutomation(id)
					commit('deleteAutomation', { stackId, automationId: id })
				} catch (error) {
					console.error('Failed to delete automation', error)
					throw error
				}
			},
		},
	}
}
