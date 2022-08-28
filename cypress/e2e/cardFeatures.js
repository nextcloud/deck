import { randHash } from '../utils'
const randUser = randHash()

const testBoardData = {
	title: 'MyBoardTest',
	color: '00ff00',
	stacks: [
		{
			title: 'TestList',
			cards: [
				{
					title: 'Hello world',
				},
			],
		},
	],
}

describe('Card', function() {
	before(function() {
		cy.nextcloudCreateUser(randUser, randUser)
		cy.createExampleBoard({
			user: randUser,
			password: randUser,
			board: testBoardData,
		})
	})

	beforeEach(function() {
		cy.login(randUser, randUser)
	})

	it('Can show card details modal', function() {
		cy.openLeftSidebar()
		cy.getNavigationEntry(testBoardData.title)
			.first().click({ force: true })

		cy.get('.board .stack').eq(0).within(() => {
			cy.get('.card:contains("Hello world")').should('be.visible').click()
		})

		cy.get('.modal__card').should('be.visible')
		cy.get('.app-sidebar-header__maintitle').contains('Hello world')
	})

	it('Can add a card', function() {
		const newCardTitle = 'Write some cypress tests'

		cy.openLeftSidebar()
		cy.getNavigationEntry(testBoardData.title)
			.first().click({ force: true })

		cy.get('.board .stack').eq(0).within(() => {
			cy.get('.card:contains("Hello world")').should('be.visible')

			cy.get('.button-vue[aria-label*="Add card"]')
				.first().click()

			cy.get('.stack__card-add form input#new-stack-input-main')
				.type(newCardTitle)
			cy.get('.stack__card-add form input[type=submit]')
				.first().click()
			cy.get(`.card:contains("${newCardTitle}")`).should('be.visible')
		})
	})

})
