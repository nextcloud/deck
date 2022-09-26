import { randHash } from '../utils'
const randUser = randHash()

describe('Board', function() {
	const password = 'pass123'

	before(function() {
		cy.nextcloudCreateUser(randUser, password)
	})

	beforeEach(function() {
		cy.login(randUser, password)
	})

	it('Can create a board', function() {
		const board = 'Test'

		cy.intercept({
			method: 'POST',
			url: '/index.php/apps/deck/boards',
		}).as('createBoardRequest')

		// Click "Add board"
		cy.openLeftSidebar()
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
})
