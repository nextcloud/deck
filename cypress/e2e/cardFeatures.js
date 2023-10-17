import { randUser } from '../utils/index.js'
import { sampleBoard } from '../utils/sampleBoard'

const user = randUser()
const boardData = sampleBoard()

const auth = {
	user: user.userId,
	password: user.password,
}

const useModal = (useModal) => {
	return cy.request({
		method: 'POST',
		url: `${Cypress.env('baseUrl')}/ocs/v2.php/apps/deck/api/v1.0/config/cardDetailsInModal?format=json`,
		auth,
		body: { value: useModal },
	}).then((response) => {
		expect(response.status).to.eq(200)
	})
}

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
	})

	it('Can add a card', function() {
		cy.visit(`/apps/deck/#/board/${boardId}`)
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

	it('Create card from overview', function() {
		cy.visit(`/apps/deck/#/`)
		const newCardTitle = 'Test create from overview'
		cy.intercept({ method: 'POST', url: '**/apps/deck/cards' }).as('save')
		cy.intercept({ method: 'GET', url: '**/apps/deck/boards/*' }).as('getBoard')

		cy.get('.button-vue[aria-label*="Add card"]')
			.first().click()
		cy.get('.modal-mask.card-selector .card-title').should('be.visible').click().type(newCardTitle)
		cy.get('.modal-mask.card-selector .multiselect-board').should('be.visible').click()
		cy.get('.modal-mask.card-selector .multiselect-board li:contains("' + boardData.title + '")').should('be.visible').click()
		cy.wait('@getBoard', { timeout: 7000 })

		cy.get('.modal-mask.card-selector .multiselect-list').should('be.visible').click()
		cy.get('.modal-mask.card-selector .multiselect-list li').eq(0).should('be.visible').click()
		cy.get('.modal-mask.card-selector button.button-vue--vue-primary').should('be.visible').click()
		cy.wait('@save', { timeout: 7000 })

		cy.visit(`/apps/deck/#/board/${boardId}`)
		cy.reload()
		cy.get('.board .stack').eq(0).within(() => {
			cy.get(`.card:contains("${newCardTitle}")`).should('be.visible')
		})
	})

	describe('Modal', () => {
		beforeEach(function() {
			cy.login(user)
			useModal(true).then(() => {
				cy.visit(`/apps/deck/#/board/${boardId}`)
			})
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

		it('Attachment from files app', () => {
			cy.get('.card:contains("Hello world")').should('be.visible').click()
			cy.get('.modal__card').should('be.visible')
			cy.get('.app-sidebar-tabs__tab [data-id="attachments"]').click()
			cy.get('button.icon-upload').should('be.visible')
			cy.get('button.icon-folder').should('be.visible')
				.click()
			cy.get('.file-picker__main').should('be.visible')
			cy.get('.file-picker__main [data-filename="welcome.txt"]', { timeout: 30000 }).should('be.visible')
				.click()
			cy.get('.dialog__actions button.button-vue--vue-primary').click()
			cy.get('.attachment-list .basename').contains('welcome.txt')
		})

		it('Shows the modal with the editor', () => {
			cy.get('.card:contains("Hello world")').should('be.visible').click()
			cy.intercept({ method: 'PUT', url: '**/apps/deck/cards/*' }).as('save')
			cy.get('.modal__card').should('be.visible')
			cy.get('.app-sidebar-header__maintitle').contains('Hello world')
			cy.get('.modal__card .ProseMirror h1').contains('Hello world').should('be.visible')
			cy.get('.modal__card .ProseMirror h1')
				.click()
				.type(' writing more text{enter}- List item{enter}with entries{enter}{enter}Paragraph')
			cy.wait('@save', { timeout: 7000 })

			cy.reload()
			cy.get('.modal__card').should('be.visible')
			cy.get('.modal__card .ProseMirror h1').contains('Hello world writing more text').should('be.visible')
			cy.get('.modal__card .ProseMirror li').eq(0).contains('List item').should('be.visible')
			cy.get('.modal__card .ProseMirror li').eq(1).contains('with entries').should('be.visible')
			cy.get('.modal__card .ProseMirror p').contains('Paragraph').should('be.visible')
		})

		it('Smart picker', () => {
			const newCardTitle = 'Test smart picker'
			cy.intercept({ method: 'POST', url: '**/apps/deck/cards' }).as('save')
			cy.intercept({ method: 'GET', url: '**/apps/deck/boards/*' }).as('getBoard')
			cy.get('.card:contains("Hello world")').should('be.visible').click()
			cy.get('.modal__card').should('be.visible')
			cy.get('.modal__card .ProseMirror h1')
				.click()
				.type('{enter}/create')
			cy.get('.suggestion-list__item.is-selected').should('be.visible').contains('Create a new deck card')
			cy.get('.suggestion-list__item.is-selected .link-picker__item').click()
			cy.get('.reference-picker-modal--content .reference-picker').should('be.visible')
			cy.get('.reference-picker-modal--content .reference-picker').contains('Create a new card')
			cy.get('.reference-picker-modal--content .reference-picker .card-title').should('be.visible').click().type(newCardTitle)
			cy.get('.reference-picker-modal--content .reference-picker .multiselect-board').should('be.visible').contains(boardData.title)
			cy.get('.reference-picker-modal--content .reference-picker .multiselect-list').should('be.visible').contains(boardData.stacks[0].title)
			cy.get('.reference-picker-modal--content .reference-picker button.button-vue--vue-primary').should('be.visible').click()
			cy.wait('@save', { timeout: 7000 })
			cy.get('.modal__card .ProseMirror').contains('/index.php/apps/deck/card/').should('be.visible')

			cy.visit(`/apps/deck/#/board/${boardId}`)
			cy.reload()
			cy.get('.board .stack').eq(0).within(() => {
				cy.get(`.card:contains("${newCardTitle}")`).should('be.visible')
			})
		})
	})

	describe('Sidebar', () => {
		beforeEach(function() {
			cy.login(user)
			useModal(false).then(() => {
				cy.visit(`/apps/deck/#/board/${boardId}`)
			})
		})

		it('Show the sidebar', () => {
			cy.get('.card:contains("Hello world")').should('be.visible').click()
			cy.get('#app-sidebar-vue')
				.find('.ProseMirror h1').contains('Hello world writing more text').should('be.visible')
		})
	})

})
