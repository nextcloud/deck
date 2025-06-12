/**
 * SPDX-FileCopyrightText: 2022 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
import { randUser } from '../utils/index.js'
import { sampleBoard } from '../utils/sampleBoard'
import moment from '@nextcloud/moment'

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
		cy.get('.vs__dropdown-menu [data-cy="board-select-title"]:contains("' + boardData.title + '")').should('be.visible').click()

		cy.wait('@getBoard', { timeout: 7000 })

		cy.get('.modal-mask.card-selector .multiselect-list').should('be.visible').click()
		cy.get('.vs__dropdown-menu span[title="TestList"]').should('be.visible').click()

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
			cy.get('.app-sidebar-header__mainname').contains('Hello world')
		})

		it('Attachment from files app', () => {
			cy.get('.card:contains("Hello world")').should('be.visible').click()
			cy.get('.modal__card').should('be.visible')
			cy.get('#tab-button-attachments').click()
			cy.get('button.icon-upload').should('be.visible')
			cy.get('button.icon-folder').should('be.visible')
				.click()
			cy.get('.file-picker__main').should('be.visible')
			cy.get('.file-picker__main [data-filename="welcome.txt"]', { timeout: 30000 }).should('be.visible')
				.click()
			cy.get('.dialog__actions button.button-vue--vue-primary').click()
			cy.get('.attachment-list .basename').contains('welcome.txt')
		})

		it.only('Shows the modal with the editor', () => {
			cy.get('.card:contains("Hello world")').should('be.visible').click()
			cy.intercept({ method: 'PUT', url: '**/apps/deck/cards/*' }).as('save')
			cy.get('.modal__card').should('be.visible')
			cy.get('.app-sidebar-header__mainname').contains('Hello world')
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

		it('Set a due date', function() {
			const newCardTitle = 'Card with a due date'

			cy.get('.button-vue[aria-label*="Add card"]')
				.first().click()
			cy.get('.stack__card-add form input#new-stack-input-main')
				.type(newCardTitle)
			cy.get('.stack__card-add form input[type=submit]')
				.first().click()
			cy.get(`.card:contains("${newCardTitle}")`).should('be.visible')

			cy.get('.card:contains("Card with a due date")').should('be.visible').click()

			cy.get('#app-sidebar-vue [data-cy-due-date-actions]').should('be.visible').click()

			// Set a due date through shortcut
			cy.get('[data-cy-due-date-shortcut="tomorrow"] button').should('be.visible').click()

			const tomorrow = moment().add(1, 'days').hour(8).minutes(0).seconds(0)
			cy.get('#card-duedate-picker').should('have.value', tomorrow.format('YYYY-MM-DDTHH:mm'))

			const now = moment().hour(11).minutes(0).seconds(0).toDate()
			cy.clock(now)
			cy.log(now)
			cy.tick(60_000)

			cy.get(`.card:contains("${newCardTitle}")`).find('[data-due-state="Now"]').should('be.visible').should('contain', '21 hours')


			// Remove the due date again
			cy.get('#app-sidebar-vue [data-cy-due-date-actions]').should('be.visible').click()
			// tick needed to show the popover menu
			cy.tick(1_000)
			cy.get('[data-cy-due-date-remove] button').should('be.visible').click()

			cy.get(`.card:contains("${newCardTitle}")`).find('[data-due-state]').should('not.exist')
		})

		it('Add a label', function() {
			const newCardTitle = 'Card with labels'

			cy.get('.button-vue[aria-label*="Add card"]')
				.first().click()
			cy.get('.stack__card-add form input#new-stack-input-main')
				.type(newCardTitle)
			cy.get('.stack__card-add form input[type=submit]')
				.first().click()
			cy.get(`.card:contains("${newCardTitle}")`).should('be.visible').click()

			cy.get('#app-sidebar-vue [data-test="tag-selector"]  .vs__dropdown-toggle').should('be.visible').click()
			cy.get('.vs__dropdown-menu .tag:contains("Action needed")').should('be.visible').click()
			cy.get('.vs__dropdown-menu .tag:contains("Later")').should('be.visible').click()

			cy.get('.vs__selected .tag:contains("Action needed")').should('be.visible')
			cy.get('.vs__selected .tag:contains("Later")').should('be.visible')
			cy.get('.vs__selected .tag:contains("Action needed")')
				.parent().find('button').click()

			cy.get(`.card:contains("${newCardTitle}")`).find('.labels li:contains("Later")')
				.should('be.visible')
			cy.get(`.card:contains("${newCardTitle}")`).find('.labels li:contains("Action needed")')
				.should('not.exist')
		})

	})

	describe('Card actions', () => {
		beforeEach(function() {
			cy.login(user)
			useModal(false).then(() => {
				cy.visit(`/apps/deck/#/board/${boardId}`)
			})
		})

		it('Custom card actions', () => {
			const myAction = {
				label: 'Test action',
				icon: 'icon-user',
				callback(card) {
					console.log('Called callback', card)
				},
			}
			cy.spy(myAction, 'callback').as('myAction.callback')

			cy.window().then(win => {
				win.OCA.Deck.registerCardAction(myAction)
			})

			cy.get('.card:contains("Hello world")').should('be.visible').click()
			cy.get('#app-sidebar-vue')
				.find('.ProseMirror h1').contains('Hello world').should('be.visible')

			cy.get('.app-sidebar-header .action-item__menutoggle').click()
			cy.get('.v-popper__popper button:contains("Test action")').click()

			cy.get('@myAction.callback')
				.should('be.called')
				.its('firstCall.args.0')
				.as('args')

			cy.url().then(url => {
				const cardId = url.split('/').pop()
				cy.get('@args').should('have.property', 'name', 'Hello world')
				cy.get('@args').should('have.property', 'stackname', 'TestList')
				cy.get('@args').should('have.property', 'boardname', 'MyTestBoard')
				cy.get('@args').its('link').then((url) => {
					expect(url.split('/').pop() === cardId).to.be.true
					cy.visit(url)
					cy.get('#app-sidebar-vue')
						.find('.ProseMirror h1').contains('Hello world').should('be.visible')
				})
			})
		})
	})
})
