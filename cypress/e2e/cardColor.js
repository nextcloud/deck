/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
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

describe('Card color', function () {
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
	})

	it('Set a color', function () {
		cy.visit(`/apps/deck/#/board/${boardId}`)

		const newCardTitle = 'Card with color'

		cy.get('.button-vue[aria-label*="Add card"]')
			.first().click()
		cy.get('.stack__card-add form input#new-stack-input-main')
			.type(newCardTitle)
		cy.get('.stack__card-add form input[type=submit]')
			.first().click()

		cy.get(`.card:contains("${newCardTitle}")`).should('be.visible')
			.click()

		cy.get('#app-sidebar-vue [data-cy-color-actions]').should('be.visible')
			.click()
		cy.get('.color-picker__simple-color-circle').first().should('be.visible')
			.click()

		cy.get('#app-sidebar-vue [data-cy-color-actions]').should('have.css', 'background-color', 'rgb(182, 70, 157)')
		cy.get(`.card:contains("${newCardTitle}")`).should('have.css', 'background-color', 'rgb(182, 70, 157)')
	})
})
