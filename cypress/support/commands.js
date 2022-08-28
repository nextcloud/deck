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

const url = Cypress.config('baseUrl').replace(/\/index.php\/?$/g, '')
Cypress.env('baseUrl', url)

Cypress.Commands.add('login', (user, password, route = '/apps/deck/') => {
	const session = `${user}-${Date.now()}`
	cy.session(session, function() {
		cy.visit(route)
		cy.get('input[name=user]').type(user)
		cy.get('input[name=password]').type(password)
		cy.get('form[name=login] [type=submit]').click()
		cy.url().should('include', route)
	})
	cy.visit(route)
})

Cypress.Commands.add('logout', (route = '/') => {
	cy.session('_guest', function() {})
})

Cypress.Commands.add('nextcloudCreateUser', (user, password) => {
	cy.clearCookies()
	cy.request({
		method: 'POST',
		url: `${Cypress.env('baseUrl')}/ocs/v1.php/cloud/users?format=json`,
		form: true,
		body: {
			userid: user,
			password,
		},
		auth: { user: 'admin', pass: 'admin' },
		headers: {
			'OCS-ApiRequest': 'true',
			'Content-Type': 'application/x-www-form-urlencoded',
		},
	}).then((response) => {
		cy.log(`Created user ${user}`, response.status)
	})
})

Cypress.Commands.add('nextcloudUpdateUser', (user, password, key, value) => {
	cy.request({
		method: 'PUT',
		url: `${Cypress.env('baseUrl')}/ocs/v2.php/cloud/users/${user}`,
		form: true,
		body: { key, value },
		auth: { user, pass: password },
		headers: {
			'OCS-ApiRequest': 'true',
			'Content-Type': 'application/x-www-form-urlencoded',
		},
	}).then((response) => {
		cy.log(`Updated user ${user} ${key} to ${value}`, response.status)
	})
})

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

Cypress.Commands.add('createExampleBoard', ({ user, password, board }) => {
	cy.request({
		method: 'POST',
		url: `${Cypress.env('baseUrl')}/index.php/apps/deck/api/v1.0/boards`,
		auth: {
			user,
			password,
		},
		body: { title: board.title, color: board.color ?? 'ff0000' },
	}).then((boardResponse) => {
		expect(boardResponse.status).to.eq(200)
		const boardData = boardResponse.body
		for (const stackIndex in board.stacks) {
			const stack = board.stacks[stackIndex]
			cy.request({
				method: 'POST',
				url: `${Cypress.env('baseUrl')}/index.php/apps/deck/api/v1.0/boards/${boardData.id}/stacks`,
				auth: {
					user,
					password,
				},
				body: { title: stack.title, order: 0 },
			}).then((stackResponse) => {
				const stackData = stackResponse.body
				for (const cardIndex in stack.cards) {
					const card = stack.cards[cardIndex]
					cy.request({
						method: 'POST',
						url: `${Cypress.env('baseUrl')}/index.php/apps/deck/api/v1.0/boards/${boardData.id}/stacks/${stackData.id}/cards`,
						auth: {
							user,
							password,
						},
						body: { title: card.title },
					})
				}
			})
		}
	})
})

Cypress.Commands.add('getNavigationEntry', (boardTitle) => {
	return cy.get('.app-navigation-entry-wrapper[icon=icon-deck]')
		.find('ul.app-navigation-entry__children .app-navigation-entry:contains(' + boardTitle + ')')
		.find('a.app-navigation-entry-link')
})
