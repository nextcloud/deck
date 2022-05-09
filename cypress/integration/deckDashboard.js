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

	/* it('Can see the default "Personal Board" created for user by default', function () {
		cy.get('.app-navigation button.app-navigation-toggle')
			.click()
		
		cy.get('.app-navigation__list .app-navigation-entry__children .app-navigation-entry')
			.first()
			.contains('Personal')
	}) */
})
