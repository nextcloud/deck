/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
import { randUser } from '../utils/index.js'
import { sampleBoard } from '../utils/sampleBoard'

const user = randUser()
const boardData = sampleBoard()

const auth = {
	user: user.userId,
	password: user.password,
}

const useModal = (useModal) => {
	return cy.request({
		method: 'POST',
		url: `${Cypress.env('baseUrl')}/ocs/v2.php/apps/deck/api/v1.0/config/cardDetailsInModal?format=json`,
		auth,
		body: { value: useModal },
	}).then((response) => {
		expect(response.status).to.eq(200)
	})
}

describe('Card actions', function () {
	let boardId
	before(function () {
		cy.createUser(user)
		cy.login(user)
		cy.createExampleBoard({
			user,
			board: boardData,
		}).then((board) => {
			boardId = board.id
		})
	})

	beforeEach(function () {
		cy.login(user)
		useModal(false).then(() => {
			cy.visit(`/apps/deck/#/board/${boardId}`)
		})
	})

	it('Custom card actions', () => {
		const myAction = {
			label: 'Test action',
			icon: 'icon-user',
			callback(card) {
				console.log('Called callback', card)
			},
		}
		cy.spy(myAction, 'callback').as('myAction.callback')

		cy.window().then(win => {
			win.OCA.Deck.registerCardAction(myAction)
		})

		cy.get('.card:contains("Hello world")').should('be.visible').click()
		cy.get('#app-sidebar-vue')
			.find('.ProseMirror h1').contains('Hello world').should('be.visible')

		cy.get('.app-sidebar-header .action-item__menutoggle').click()
		cy.get('.v-popper__popper button:contains("Test action")').click()

		cy.get('@myAction.callback')
			.should('be.called')
			.its('firstCall.args.0')
			.as('args')

		cy.url().then(url => {
			const cardId = url.split('/').pop()
			cy.get('@args').should('have.property', 'name', 'Hello world')
			cy.get('@args').should('have.property', 'stackname', 'TestList')
			cy.get('@args').should('have.property', 'boardname', 'MyTestBoard')
			cy.get('@args').its('link').then((url) => {
				expect(url.split('/').pop() === cardId).to.be.true
				cy.visit(url)
				cy.get('#app-sidebar-vue')
					.find('.ProseMirror h1').contains('Hello world').should('be.visible')
			})
		})
	})

	it('clone card', () => {
			cy.intercept({ method: 'POST', url: '**/apps/deck/**/cards/*/clone' }).as('clone')
			cy.get('.card:contains("Hello world")').should('be.visible').click()
			cy.get('#app-sidebar-vue')
				.find('.ProseMirror h1').contains('Hello world').should('be.visible')

			cy.get('.app-sidebar-header .action-item__menutoggle').click()
			cy.get('.v-popper__popper button:contains("Move/copy card")').click()
			cy.get('.vs__dropdown-menu span[title="MyTestBoard"]').should('be.visible').click()
			cy.wait(3000) // wait for select component to load stacks
			cy.get('[data-cy="select-stack"] .vs__dropdown-toggle').should('be.visible').click()
			cy.get('.vs__dropdown-menu span[title="TestList"]').should('be.visible').click()
			cy.get('.modal-container button:contains("Copy card")').click()
			cy.wait('@clone', { timeout: 7000 })
			cy.get('.card:contains("Hello world")').should('have.length', 2)
		})
})
