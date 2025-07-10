/**
 * SPDX-FileCopyrightText: 2022 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
import { randUser } from '../utils/index.js'
import { sampleBoard } from '../utils/sampleBoard'
const user = randUser()

describe('Deck dashboard', function() {
	before(function() {
		cy.createUser(user)
	})

	beforeEach(function() {
		cy.login(user)
	})

	it('Can show the right title on the dashboard', function() {
		cy.visit('/apps/deck')
		cy.get('.board-title h2')
			.should('have.length', 1).first()
			.should($el => expect($el.text().trim()).to.equal('Upcoming cards'))
	})

	it('Can see the default "Welcome Board" created for user by default', function() {
		cy.visit('/apps/deck')

		const defaultBoard = 'Welcome to Nextcloud Deck!'

		cy.get('#deck-navigation-all')
			.find('ul.app-navigation-entry__children .app-navigation-entry:contains(' + defaultBoard + ')')
			.first()
			.contains(defaultBoard)
			.should('be.visible')
	})

	it('Shows a card with due date on the overview', function() {
		cy.createExampleBoard({
			user,
			board: sampleBoard(),
		}).then((board) => {
			cy.visit(`/apps/deck/#/board/${board.id}`)

			cy.intercept({ method: 'PUT', url: '**/apps/deck/cards/**' }).as('updateCard')

			const newCardTitle = 'Hello world'
			cy.get(`.card:contains("${newCardTitle}")`).should('be.visible').click()
			cy.get('#app-sidebar-vue [data-cy-due-date-actions]').should('be.visible').click()
			cy.get('[data-cy-due-date-shortcut="tomorrow"] button').should('be.visible').click()

			cy.wait('@updateCard')

			cy.get('button[title="Close sidebar"]').click()
			cy.get('.app-navigation-entry:contains("Upcoming cards") a').click()

			cy.get(`.card:contains("${newCardTitle}")`).should('be.visible')
			cy.get('.dashboard-column:contains("Tomorrow")').should('be.visible')
			cy.get('.dashboard-column:contains("Tomorrow") .card:contains("Hello world")').should('be.visible')
		})
	})
})
