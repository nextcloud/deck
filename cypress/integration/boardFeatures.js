import { randHash } from "../utils"
const randUser = randHash()

describe('Board', function () {
    const password = 'pass123'
    
    before(function () {
		cy.nextcloudCreateUser(randUser, password)
	})

	beforeEach(function() {
		cy.login(randUser, password)
	})

    it('Can create a board', function () {
        let board = 'Test'
            
        // Click "Add board"
        cy.get('.app-navigation button.app-navigation-toggle').click()
        cy.get('#app-navigation-vue .app-navigation__list .app-navigation-entry')
            .eq(1).find('a').first().click({force: true})

        // Type the board title
        cy.get('.board-create form input[type=text]')
            .type(board, {force: true})

        // Submit
        cy.get('.board-create form input[type=submit]')
            .first().click({force: true})
        
        cy.get('.app-navigation__list .app-navigation-entry__children .app-navigation-entry')
            .first().contains(board).should('be.visible')
	})
})