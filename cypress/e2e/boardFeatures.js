/**
 * SPDX-FileCopyrightText: 2022 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
import { randUser } from '../utils/index.js'
const user = randUser()
const recipient = randUser()

describe('Board', function() {

	before(function() {
		cy.createUser(user)
		cy.createUser(recipient)
	})

	beforeEach(function() {
		cy.login(user)
		cy.visit('/apps/deck')
	})

	it('Can create a board', function() {
		const board = 'TestBoard'

		cy.intercept({
			method: 'POST',
			url: '/index.php/apps/deck/boards',
		}).as('createBoardRequest')

		// Click "Add board"
		cy.get('#app-navigation-vue .app-navigation__list .app-navigation-entry')
			.eq(3).find('a').first().click({ force: true })

		// Type the board title
		cy.get('.board-create form input[type=text]')
			.type(board, { force: true })

		// Submit
		cy.get('.board-create form input[type=submit]')
			.first().click({ force: true })

		cy.wait('@createBoardRequest').its('response.statusCode').should('equal', 200)

		cy.get('.app-navigation__list .app-navigation-entry__children .app-navigation-entry')
			.contains(board).should('be.visible')
	})

	it('Shows and hides the navigation', () => {
		cy.get('#app-navigation-vue .app-navigation__list .app-navigation-entry')
			.contains('Upcoming cards')
			.should('be.visible')
		cy.openLeftSidebar()
		cy.get('#app-navigation-vue .app-navigation__list .app-navigation-entry')
			.contains('Upcoming cards')
			.should('not.be.visible')
		cy.openLeftSidebar()
		cy.get('#app-navigation-vue .app-navigation__list .app-navigation-entry')
			.contains('Upcoming cards')
			.should('be.visible')
	})
})
