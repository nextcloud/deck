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
	cy.session(user, function () {
		cy.visit(route)
		cy.get('input[name=user]').type(user)
		cy.get('input[name=password]').type(password)
		cy.get('#submit-wrapper input[type=submit]').click()
		cy.url().should('include', route)
	})
	// in case the session already existed but we are on a different route...
	cy.visit(route)
})
 
 Cypress.Commands.add('logout', (route = '/') => {
	 cy.session('_guest', function () {
	 })
 })
 
 Cypress.Commands.add('nextcloudCreateUser', (user, password) => {
	 cy.clearCookies()
	 cy.request({
		 method: 'POST',
		 url: `${Cypress.env('baseUrl')}/ocs/v1.php/cloud/users?format=json`,
		 form: true,
		 body: {
			 userid: user,
			 password: password
		 },
		 auth: { user: 'admin', pass: 'admin' },
		 headers: {
			 'OCS-ApiRequest': 'true',
			 'Content-Type': 'application/x-www-form-urlencoded',
		 }
	 }).then(response => {
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
		 }
	 }).then(response => {
		 cy.log(`Updated user ${user} ${key} to ${value}`, response.status)
	 })
 })
 
