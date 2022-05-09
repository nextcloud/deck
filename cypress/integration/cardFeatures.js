import { randHash } from '../utils'
const randUser = randHash()

describe('Card', function () {
    const board = 'TestBoard'
    const list = 'TestList'
    const password = 'pass123'

    before(function () {
        cy.nextcloudCreateUser(randUser, password)
        cy.deckCreateBoard({ user: randUser, password }, board)
        cy.deckCreateList({ user: randUser, password }, list)
    })

    beforeEach(function () {
        cy.login(randUser, password)
    })

    it('Can add a card', function () {
        let card = 'Card 1'

        cy.get('.app-navigation button.app-navigation-toggle').click()
        cy.get('#app-navigation-vue .app-navigation__list .app-navigation-entry')
            .eq(3).find('a.app-navigation-entry-link')
            .first().click({force: true})

        cy.get('.board .stack').eq(0).within(() => {
            cy.get('button.action-item.action-item--single.icon-add')
                .first().click()

            cy.get('.stack__card-add form input#new-stack-input-main')
                .type(card)
            cy.get('.stack__card-add form input[type=submit]')
                .first().click()
            cy.get('.card').first().contains(card).should('be.visible')
          })
    })
})