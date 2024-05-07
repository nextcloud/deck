/**
 * SPDX-FileCopyrightText: 2022 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
import { randUser } from '../utils/index.js'
const user = randUser()

const boardTitle = 'TestBoard'
const testBoardData = {
	title: boardTitle,
	stacks: [
		{ title: 'Existing Stack1' },
		{ title: 'Existing Stack2' },
	],
}

describe('Stack', function() {

	before(function() {
		cy.createUser(user)
		cy.login(user)
		cy.createExampleBoard({
			user,
			board: testBoardData,
		})
	})

	beforeEach(function() {
		cy.login(user)
		cy.visit('/apps/deck')

		cy.openLeftSidebar()
		cy.getNavigationEntry(boardTitle)
			.click({ force: true })
	})

	it('Can create a stack', function() {
		cy.get('#stack-add button').first().click()
		cy.focused().type('List 1')
		cy.get('#stack-add form input[type=submit]').first().click()

		cy.contains('List 1').should('be.visible')
	})

	it('Can edit a stack title', function() {
		cy.contains('Existing Stack1')
		cy.get('[data-cy-stack="Existing Stack1"]').within(() => {
			cy.contains('Existing Stack1').click()
			cy.focused().type(' renamed')
			cy.get('[data-cy="editStackTitleForm"] input[type="submit"]').click()
		})
		cy.contains('Existing Stack1 renamed').should('be.visible')
	})

	it('Can abort a stack title edit via esc', function() {
		cy.contains('Existing Stack2').click()
		cy.focused().type(' with a new title, maybe?')
		cy.focused().type('{esc}')

		cy.contains('Existing Stack2').should('be.visible')
		cy.contains('Existing Stack2 with a new title, maybe?').should('not.exist')
	})

	it('Can abort a stack title edit via click outside', function() {
		cy.contains('Existing Stack2').click()
		cy.focused().type(' with a new title, maybe?')
		cy.get('[data-cy-stack="Existing Stack2"]').click('bottom')

		cy.contains('Existing Stack2').should('be.visible')
		cy.contains('Existing Stack2 with a new title, maybe?').should('not.exist')
	})
})
