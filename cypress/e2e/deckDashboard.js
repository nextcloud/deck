import { randUser } from '../utils/index.js'
const user = randUser()

describe('Deck dashboard', function() {
	before(function() {
		cy.createUser(user)
	})

	beforeEach(function() {
		cy.login(user)
		cy.visit('/apps/deck')
	})

	it('Can show the right title on the dashboard', function() {
		cy.get('.board-title h2')
			.should('have.length', 1).first()
			.should($el => expect($el.text().trim()).to.equal('Upcoming cards'))
	})

	it('Can see the default "Personal Board" created for user by default', function() {
		const defaultBoard = 'Personal'

		cy.get('.app-navigation-entry-wrapper[icon=icon-deck]')
			.find('ul.app-navigation-entry__children .app-navigation-entry:contains(' + defaultBoard + ')')
			.first()
			.contains(defaultBoard)
			.should('be.visible')
	})
})
