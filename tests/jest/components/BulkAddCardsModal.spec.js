/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { shallowMount, createLocalVue } from '@vue/test-utils'
import Vuex from 'vuex'

// Mock @nextcloud modules before importing the component
jest.mock('@nextcloud/vue', () => ({
	NcDialog: { template: '<div><slot /><slot name="actions" /></div>', props: ['open', 'name', 'size'] },
	NcButton: { template: '<button :disabled="disabled"><slot /></button>', props: ['type', 'disabled'] },
	NcSelect: { template: '<select><slot /></select>', props: ['value', 'options', 'clearable', 'label', 'inputLabel', 'placeholder'] },
	NcTextField: { template: '<input :disabled="disabled" />', props: ['value', 'label', 'placeholder', 'disabled'] },
}))
jest.mock('@nextcloud/dialogs', () => ({
	showError: jest.fn(),
}))
jest.mock('vue-material-design-icons/Plus.vue', () => ({ template: '<span />' }))

import BulkAddCardsModal from '@/components/BulkAddCardsModal.vue'

const localVue = createLocalVue()
localVue.use(Vuex)
localVue.mixin({
	methods: {
		t: (app, str) => str,
		n: (app, singular, plural, count) => (count === 1 ? singular : plural),
	},
})

const stacks = [
	{ id: 1, title: 'To do', boardId: 10, order: 0 },
	{ id: 2, title: 'Doing', boardId: 10, order: 1 },
	{ id: 3, title: 'Done', boardId: 10, order: 2 },
]

const board = { id: 10, title: 'Test Board' }

function createStore(overrides = {}) {
	return new Vuex.Store({
		getters: {
			stacksByBoard: () => () => stacks,
			...overrides.getters,
		},
		actions: {
			addCard: jest.fn(() => Promise.resolve({ id: 99, title: 'New card' })),
			...overrides.actions,
		},
	})
}

function mountComponent(store, propsData = {}) {
	return shallowMount(BulkAddCardsModal, {
		localVue,
		store,
		propsData: {
			board,
			...propsData,
		},
	})
}

describe('BulkAddCardsModal', () => {
	it('pre-selects the first stack on mount', () => {
		const store = createStore()
		const wrapper = mountComponent(store)

		expect(wrapper.vm.selectedStack).toEqual(stacks[0])
	})

	it('does not pre-select a stack when no stacks are available', () => {
		const store = createStore({
			getters: { stacksByBoard: () => () => [] },
		})
		const wrapper = mountComponent(store)

		expect(wrapper.vm.selectedStack).toBeNull()
	})

	it('computes canCreate correctly', async () => {
		const store = createStore()
		const wrapper = mountComponent(store)

		// Has stack but no title
		expect(wrapper.vm.canCreate).toBe(false)

		// Has stack and title
		wrapper.setData({ cardTitle: 'My card' })
		await wrapper.vm.$nextTick()
		expect(wrapper.vm.canCreate).toBe(true)

		// Whitespace-only title
		wrapper.setData({ cardTitle: '   ' })
		await wrapper.vm.$nextTick()
		expect(wrapper.vm.canCreate).toBe(false)
	})

	it('disables the input when no stack is selected', () => {
		const store = createStore({
			getters: { stacksByBoard: () => () => [] },
		})
		const wrapper = mountComponent(store)
		const textField = wrapper.findComponent({ name: 'NcTextField' })

		expect(textField.props('disabled')).toBe(true)
	})

	it('dispatches addCard with correct payload and clears title', async () => {
		const addCard = jest.fn(() => Promise.resolve({ id: 99, title: 'Buy milk' }))
		const store = createStore({ actions: { addCard } })
		const wrapper = mountComponent(store)

		wrapper.setData({ cardTitle: 'Buy milk' })
		await wrapper.vm.createCard()

		expect(addCard).toHaveBeenCalledWith(expect.anything(), {
			title: 'Buy milk',
			stackId: 1,
			boardId: 10,
		})
		expect(wrapper.vm.cardTitle).toBe('')
	})

	it('does not dispatch addCard when canCreate is false', async () => {
		const addCard = jest.fn(() => Promise.resolve())
		const store = createStore({ actions: { addCard } })
		const wrapper = mountComponent(store)

		// Empty title
		await wrapper.vm.createCard()
		expect(addCard).not.toHaveBeenCalled()
	})

	it('trims whitespace from card title before creating', async () => {
		const addCard = jest.fn(() => Promise.resolve({ id: 99, title: 'Trimmed' }))
		const store = createStore({ actions: { addCard } })
		const wrapper = mountComponent(store)

		wrapper.setData({ cardTitle: '  Trimmed  ' })
		await wrapper.vm.createCard()

		expect(addCard).toHaveBeenCalledWith(expect.anything(), {
			title: 'Trimmed',
			stackId: 1,
			boardId: 10,
		})
	})

	it('emits close when done is called', () => {
		const store = createStore()
		const wrapper = mountComponent(store)

		wrapper.vm.done()

		expect(wrapper.emitted('close')).toHaveLength(1)
	})

	it('sets isCreating during card creation', async () => {
		let resolvePromise
		const addCard = jest.fn(() => new Promise((resolve) => { resolvePromise = resolve }))
		const store = createStore({ actions: { addCard } })
		const wrapper = mountComponent(store)

		wrapper.setData({ cardTitle: 'Test' })
		const createPromise = wrapper.vm.createCard()

		expect(wrapper.vm.isCreating).toBe(true)
		expect(wrapper.vm.canCreate).toBe(false)

		resolvePromise({ id: 99, title: 'Test' })
		await createPromise

		expect(wrapper.vm.isCreating).toBe(false)
	})
})
