/**
 * @copyright Copyright (c) 2019 John Molakvoæ <skjnldsv@protonmail.com>
 *
 * @author John Molakvoæ <skjnldsv@protonmail.com>
 *
 * @license GNU AGPL version 3 or any later version
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 *
 */

import { addCommands } from '@nextcloud/cypress'
import axios from '@nextcloud/axios'

addCommands()

const url = Cypress.config('baseUrl').replace(/\/index.php\/?$/g, '')
Cypress.env('baseUrl', url)

// prepare main cypress window so we can use axios there
// and it will successfully fetch csrf tokens when needed.
window.OC = {
	config: { modRewriteWorking: false },
}
// Prevent @nextcloud/router from reading window.location
window._oc_webroot = url

Cypress.Commands.add('openLeftSidebar', () => {
	cy.get('.app-navigation button.app-navigation-toggle').click()
})

Cypress.Commands.add('deckCreateBoard', ({ user, password }, title) => {
	cy.login(user, password)

	cy.get('.app-navigation button.app-navigation-toggle').click()
	cy.get('#app-navigation-vue .app-navigation__list .app-navigation-entry')
		.eq(3)
		.find('a')
		.first()
		.click({ force: true })

	cy.get('.board-create form input[type=text]').type(title, { force: true })

	cy.get('.board-create form input[type=submit]')
		.first()
		.click({ force: true })
})

Cypress.Commands.add('deckCreateList', ({ user, password }, title) => {
	cy.login(user, password)

	cy.get('.app-navigation button.app-navigation-toggle').click()
	cy.get('#app-navigation-vue .app-navigation__list .app-navigation-entry')
		.eq(3)
		.find('a.app-navigation-entry-link')
		.first()
		.click({ force: true })

	cy.get('#stack-add button').first().click()
	cy.get('#stack-add form input#new-stack-input-main').type(title)
	cy.get('#stack-add form input[type=submit]').first().click()
})

Cypress.Commands.add('createExampleBoard', ({ user, board }) => {
	const auth = {
		user: user.userId,
		password: user.password,
	}
	cy.request({
		method: 'POST',
		url: `${Cypress.env('baseUrl')}/index.php/apps/deck/api/v1.0/boards`,
		auth,
		body: { title: board.title, color: board.color ?? 'ff0000' },
	}).then((boardResponse) => {
		expect(boardResponse.status).to.eq(200)
		const boardData = boardResponse.body
		for (const stackIndex in board.stacks) {
			const stack = board.stacks[stackIndex]
			cy.request({
				method: 'POST',
				url: `${Cypress.env('baseUrl')}/index.php/apps/deck/api/v1.0/boards/${boardData.id}/stacks`,
				auth,
				body: { title: stack.title, order: 0 },
			}).then((stackResponse) => {
				const stackData = stackResponse.body
				for (const cardIndex in stack.cards) {
					const card = stack.cards[cardIndex]
					cy.request({
						method: 'POST',
						url: `${Cypress.env('baseUrl')}/index.php/apps/deck/api/v1.0/boards/${boardData.id}/stacks/${stackData.id}/cards`,
						auth,
						body: { title: card.title, description: card.description ?? '' },
					})
				}
			})
		}
		cy.wrap(boardData)
	})
})

Cypress.Commands.add('getNavigationEntry', (boardTitle) => {
	return cy.get('.app-navigation-entry-wrapper[icon=icon-deck]')
		.find('ul.app-navigation-entry__children .app-navigation-entry:contains(' + boardTitle + ')')
		.find('a.app-navigation-entry-link')
})

Cypress.Commands.add('shareBoardWithUi', (query, userId=query) => {
	cy.intercept({ method: 'GET', url: `**/ocs/v2.php/apps/files_sharing/api/v1/sharees?search=${query}*` }).as('fetchRecipients')
	cy.get('[aria-label="Open details"]').click()
	cy.get('.app-sidebar').should('be.visible')
	cy.get('.select input').type(`${query}`)
	cy.wait('@fetchRecipients', { timeout: 7000 })

	cy.get('.vs__dropdown-menu .option').first().contains(query)
	cy.get('.select input').type('{enter}')

	cy.get('.shareWithList').contains(userId)
})

Cypress.Commands.add('setUserEmail', (user, value) => {
	Cypress.log()
	return axios.put(
		`${url}/ocs/v2.php/cloud/users/${user.userId}`,
		{ key: 'email', value },
	)
})
