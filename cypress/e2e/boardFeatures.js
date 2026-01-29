/**
 * SPDX-FileCopyrightText: 2022 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
import { randUser } from '../utils/index.js'
const user = randUser()
const recipient = randUser()
import { sampleBoard } from '../utils/sampleBoard'

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
		cy.get('.board-create form button[type=submit]')
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

describe('Board cloning', function() {
	before(function() {
		cy.createUser(user)
	})

	it('Clones a board without cards', function() {
		const boardName = 'Clone board original'
		const board = sampleBoard(boardName)
		cy.createExampleBoard({ user, board }).then((board) => {
			const boardId = board.id
			cy.visit(`/apps/deck/board/${boardId}`)
			cy.get('.app-navigation__list .app-navigation-entry:contains("' + boardName + '")')
				.parent()
				.find('button[aria-label="Actions"]')
				.click()
			cy.get('button:contains("Clone board")')
				.click()

			cy.get('.modal-container button:contains("Clone")')
				.click()

			cy.get('.app-navigation__list .app-navigation-entry:contains("' + boardName + '")')
				.should('be.visible')

			cy.get('.app-navigation__list .app-navigation-entry:contains("' + boardName + ' (copy)")')
				.should('be.visible')

			cy.get('.board-title h2').contains(boardName + ' (copy)')

			cy.get('h3[aria-label="TestList"]')
				.should('be.visible')
		})
	})

	it('Clones a board with cards', function() {
		const boardName = 'Clone with cards'
		const board = sampleBoard(boardName)
		cy.createExampleBoard({ user, board }).then((board) => {
			const boardId = board.id
			cy.visit(`/apps/deck/board/${boardId}`)
			cy.get('.app-navigation__list .app-navigation-entry:contains("' + boardName + '")')
				.parent()
				.find('button[aria-label="Actions"]')
				.click()
			cy.get('button:contains("Clone board")')
				.click()

			cy.get('.checkbox-content__text:contains("Clone cards")')
				.click()

			cy.get('.modal-container button:contains("Clone")')
				.click()

			cy.get('.app-navigation__list .app-navigation-entry:contains("' + boardName + '")')
				.should('be.visible')

			cy.get('.app-navigation__list .app-navigation-entry:contains("' + boardName + ' (copy)")')
				.should('be.visible')

			cy.get('.board-title h2').contains(boardName + ' (copy)')

			cy.get('h3[aria-label="TestList"]')
				.should('be.visible')

			cy.get('.card:contains("Hello world")')
				.should('be.visible')
		})
	})
})

describe('Board export', function() {
	before(function() {
		cy.createUser(user)
	})

	it('Exports a board as JSON', function() {
		const boardName = 'Export JSON board'
		const board = sampleBoard(boardName)
		cy.createExampleBoard({ user, board }).then((board) => {
			const boardId = board.id
			cy.visit(`/apps/deck/board/${boardId}`)
			cy.get('.app-navigation__list .app-navigation-entry:contains("' + boardName + '")')
				.parent()
				.find('button[aria-label="Actions"]')
				.click()
			cy.get('button:contains("Export board")')
				.click()
			cy.get('.modal-container .checkbox-radio-switch__text:contains("Export as JSON")')
				.click()
			cy.get('.modal-container button:contains("Export")')
				.click()

			const downloadsFolder = Cypress.config('downloadsFolder')
			cy.readFile(`${downloadsFolder}/${boardName}.json`)
		})
	})

	it('Exports a board as CSV', function() {
		const boardName = 'Export CSV board'
		const board = sampleBoard(boardName)
		cy.createExampleBoard({ user, board }).then((board) => {
			const boardId = board.id
			cy.visit(`/apps/deck/board/${boardId}`)
			cy.get('.app-navigation__list .app-navigation-entry:contains("' + boardName + '")')
				.parent()
				.find('button[aria-label="Actions"]')
				.click()
			cy.get('button:contains("Export board")')
				.click()
			cy.get('.modal-container .checkbox-radio-switch__text:contains("Export as CSV")')
				.click()
			cy.get('.modal-container button:contains("Export")')
				.click()

			const downloadsFolder = Cypress.config('downloadsFolder')
			cy.readFile(`${downloadsFolder}/${boardName}.csv`)
		})
	})
})

describe('Board title editing', function() {
	before(function() {
		cy.createUser(user)
	})

	it('Shows updated board title immediately on the opened board', function() {
		const originalTitle = `Live rename ${Date.now()}`
		const updatedTitle = `${originalTitle} updated`

		cy.createExampleBoard({ user, board: sampleBoard(originalTitle) }).then((board) => {
			cy.login(user)
			cy.visit(`/apps/deck/board/${board.id}`)

			cy.intercept({ method: 'PUT', url: `**/apps/deck/boards/${board.id}` }).as('updateBoard')

			cy.get(`.app-navigation__list .app-navigation-entry:contains("${originalTitle}")`)
				.parent()
				.find('button[aria-label="Actions"]')
				.click()

			cy.get('button:contains("Edit board")').click()

			cy.get('.board-edit form input[type=text]')
				.clear()
				.type(updatedTitle)

			cy.get('.board-edit form button[title="Save board"]').click()

			cy.get('.board-title h2').contains(updatedTitle)

			cy.wait('@updateBoard').its('response.statusCode').should('equal', 200)

			cy.get('.app-navigation__list .app-navigation-entry')
				.contains(updatedTitle)
				.should('be.visible')
		})
	})

	it('Does not change the opened board title when editing another board', function() {
		const boardATitle = `Active board ${Date.now()}`
		const boardBTitle = `Background board ${Date.now()}`
		const boardBUpdatedTitle = `${boardBTitle} updated`

		cy.createExampleBoard({ user, board: sampleBoard(boardATitle) }).then((boardA) => {
			cy.createExampleBoard({ user, board: sampleBoard(boardBTitle) }).then((boardB) => {
				cy.login(user)
				cy.visit(`/apps/deck/board/${boardA.id}`)

				cy.intercept({ method: 'PUT', url: `**/apps/deck/boards/${boardB.id}` }).as('updateBoardOther')

				cy.get('.board-title h2').should('contain', boardATitle)

				cy.get(`.app-navigation__list .app-navigation-entry:contains("${boardBTitle}")`)
					.parent()
					.find('button[aria-label="Actions"]')
					.click()

				cy.get('button:contains("Edit board")').click()

				cy.get('.board-edit form input[type=text]')
					.clear()
					.type(boardBUpdatedTitle)

				cy.get('.board-edit form button[title="Save board"]').click()

				cy.wait('@updateBoardOther').its('response.statusCode').should('equal', 200)

				cy.get('.board-title h2').should('contain', boardATitle)
				cy.get('.app-navigation__list .app-navigation-entry')
					.contains(boardBUpdatedTitle)
					.should('be.visible')
			})
		})
	})
})

describe('Board import', function() {
	before(function () {
		cy.createUser(user)
	})
	beforeEach(function() {
		cy.login(user)
		cy.visit('/apps/deck')
	})

	it('Imports a board from JSON', function() {
		cy.get('#app-navigation-vue .app-navigation__list .app-navigation-entry:contains("Import board")')
			.should('be.visible')
			.click()

		// Upload a JSON file
		cy.get('input[type="file"]')
			.selectFile([
				{
					contents: 'cypress/fixtures/import-board.json',
					fileName: 'import-board.json',
				},
			], { force: true })

		cy.get('.app-navigation__list .app-navigation-entry:contains("Imported board")')
			.should('be.visible')
	})
})
