import { randHash } from '../utils'
const randUser = randHash()

describe('Stack', function() {
	const board = 'TestBoard'
	const password = 'pass123'
	const stack = 'List 1'

	before(function() {
		cy.nextcloudCreateUser(randUser, password)
		cy.deckCreateBoard({ user: randUser, password }, board)
	})

	beforeEach(function() {
		cy.logout()
		cy.login(randUser, password)
	})

	it('Can create a stack', function() {
		cy.openLeftSidebar()
		cy.getNavigationEntry(board)
			.click({ force: true })

		cy.get('#stack-add button').first().click()
		cy.get('#stack-add form input#new-stack-input-main').type(stack)
		cy.get('#stack-add form input[type=submit]').first().click()

		cy.get('.board .stack').eq(0).contains(stack).should('be.visible')
	})
})
