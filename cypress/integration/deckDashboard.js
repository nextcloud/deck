import { randHash } from '../utils'
const randUser = randHash()

describe('Deck dashboard', function() {
	before(function () {
		// Create a user
		cy.nextcloudCreateUser(randUser, 'pass123')
	})

	beforeEach(function() {
		cy.login(randUser, 'pass123')
	})

	it('Can show the right title on the dashboard', function() {
		cy.get('.board-title h2')
        	.should('have.length', 1)
        	.first()
        	.should('have.text', 'Upcoming cards')
	})

	/* it('Can see the default "Personal Board" created for user by default', function () {
		cy.get('.app-navigation button.app-navigation-toggle')
			.click()
		
		cy.get('.app-navigation__list .app-navigation-entry__children .app-navigation-entry')
			.first()
			.contains('Personal')
	}) */

	it('Can create a board', function () {
		cy.get('#app-navigation-vue .app-navigation__list .app-navigation-entry')
			.eq(1)
			.find('a')
			.first()
			.click({force: true})

		cy.get('.board-create form input[type=text]')
			.type('Test', {force: true})

		cy.get('.board-create form input[type=submit]')
			.first()
			.click({force: true})

	})
})
