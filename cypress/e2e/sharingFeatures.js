/**
 * SPDX-FileCopyrightText: 2022 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
import { randUser } from '../utils/index.js'
import { sampleBoard } from '../utils/sampleBoard'
const user = randUser()
const recipient = randUser()

describe('Board', function() {
	before(function() {
		cy.createUser(user)
		cy.createUser(recipient)
	})

	beforeEach(function() {
		cy.login(user)
	})

	it('Share a board to a user', function() {
		const board = sampleBoard('Read only board')
		cy.createExampleBoard({ user, board }).then((board) => {
			const boardId = board.id
			cy.visit(`/apps/deck/#/board/${boardId}`)
			cy.get('.board-title').contains(board.title)

			cy.shareBoardWithUi(recipient.userId)

			cy.login(recipient)
			cy.visit(`/apps/deck/#/board/${boardId}`)
			cy.get('.board-title').contains(board.title)
			cy.get('.button-vue[aria-label*="Add card"]')
				.should('not.exist')
		})
	})

	it('Share a board to a user as writable', function() {
		const board = sampleBoard('Editable board')
		cy.createExampleBoard({ user, board }).then((board) => {
			const boardId = board.id
			cy.visit(`/apps/deck/#/board/${boardId}`)
			cy.get('.board-title').contains(board.title)

			cy.shareBoardWithUi(recipient.userId)

			cy.intercept({ method: 'PUT', url: '**/apps/deck/boards/*/acl/*' }).as('setAcl')
			cy.get(`[data-cy="acl-participant:${recipient.userId}"]`).find('[data-cy="action:permission-edit"]').click()
			cy.wait('@setAcl')

			cy.login(recipient)
			cy.visit(`/apps/deck/#/board/${boardId}`)
			cy.get('.board-title').contains(board.title)
			cy.get('.button-vue[aria-label*="Add card"]')
				.first().click()
		})
	})
})
