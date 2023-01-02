import { randUser } from '../utils/index.js'
import { sampleBoard } from '../utils/sampleBoard'

const user = randUser()
const boardData = sampleBoard()

describe('Card', function() {
	let boardId
	before(function() {
		cy.createUser(user)
		cy.login(user)
		cy.createExampleBoard({
			user,
			board: boardData,
		}).then((board) => {
			boardId = board.id
		})
	})

	beforeEach(function() {
		cy.login(user)
		cy.visit(`/apps/deck/#/board/${boardId}`)
	})

	it('Can show card details modal', function() {
		cy.getNavigationEntry(boardData.title)
			.first().click({ force: true })

		cy.get('.board .stack').eq(0).within(() => {
			cy.get('.card:contains("Hello world")').should('be.visible').click()
		})

		cy.get('.modal__card').should('be.visible')
		cy.get('.app-sidebar-header__maintitle').contains('Hello world')
	})

	it('Can add a card', function() {
		const newCardTitle = 'Write some cypress tests'

		cy.getNavigationEntry(boardData.title)
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
