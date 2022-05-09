import { randHash } from '../utils'
const randUser = randHash()

describe('Deck dashboard', function() {
	const password = 'pass123'

	before(function () {
		cy.nextcloudCreateUser(randUser, password)
	})

	beforeEach(function() {
		cy.login(randUser, password)
	})

	it('Can show the right title on the dashboard', function() {
		cy.get('.board-title h2')
        	.should('have.length', 1).first()
        	.should('have.text', 'Upcoming cards')
	})

	it('Can see the default "Personal Board" created for user by default', function () {
		const defaultBoard = 'Personal'

		cy.get('.app-navigation button.app-navigation-toggle').click()
		
		cy.get('.app-navigation__list .app-navigation-entry')
			.eq(1)
			.find('ul.app-navigation-entry__children li.app-navigation-entry')
			.first()
			.contains(defaultBoard)
			.should('be.visible')
	})
})