/**
 * SPDX-FileCopyrightText: 2022 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
import { randUser } from '../utils/index.js'
import { sampleBoard } from '../utils/sampleBoard'
const user = randUser()
const recipient = randUser()
const domain = Math.random().toString(36).replace(/[^a-z]+/g, '').slice(0, 10)

describe('Board', function() {
	before(function() {
		cy.createUser(user)
		cy.createUser(recipient)
		cy.login(recipient)
		cy.setUserEmail(recipient, `${recipient.userId}@${domain}.com`)
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

	it('Share a board to a user by email', function() {
		const board = sampleBoard('Shared by email')
		cy.createExampleBoard({ user, board }).then((board) => {
			const boardId = board.id
			cy.visit(`/apps/deck/#/board/${boardId}`)
			cy.get('.board-title').contains(board.title)

			// domain is only in the email address - not in user ids.
			cy.shareBoardWithUi(domain, recipient.userId)

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
