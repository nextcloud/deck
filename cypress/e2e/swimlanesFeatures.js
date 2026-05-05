/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
import { randUser } from '../utils/index.js'

const user = randUser()

// Builds a board with three stacks, three labels, two cards \u2014 one card
// has two labels so it should render in two lanes when grouped by labels.
function seedSwimlaneBoard() {
	const auth = { user: user.userId, password: user.password }
	const baseUrl = Cypress.env('baseUrl')
	const api = `${baseUrl}/index.php/apps/deck/api/v1.0`

	return cy.request({
		method: 'POST',
		url: `${api}/boards`,
		auth,
		body: { title: 'Swimlanes', color: '00ff00' },
	}).then(({ body: board }) => {
		const boardId = board.id

		const stackReq = (title) => cy.request({
			method: 'POST',
			url: `${api}/boards/${boardId}/stacks`,
			auth,
			body: { title, order: 0 },
		})
		const labelReq = (title, color) => cy.request({
			method: 'POST',
			url: `${api}/boards/${boardId}/labels`,
			auth,
			body: { title, color },
		}).then(({ body }) => body)

		return Cypress.Promise.all([
			stackReq('Todo'), stackReq('Doing'), stackReq('Done'),
			labelReq('Bug', 'ff0000'), labelReq('Feature', '00ff00'),
			labelReq('Backend', '0000ff'),
		]).then((results) => {
			const [todoStack, , , bug, feature, backend] = results.map((r) => r.body ?? r)
			const todoId = todoStack.id
			const mk = (title) => cy.request({
				method: 'POST',
				url: `${api}/boards/${boardId}/stacks/${todoId}/cards`,
				auth,
				body: { title, description: '' },
			}).then(({ body }) => body)
			return cy.wrap(null).then(() =>
				mk('Fix login bug').then((card) =>
					cy.request({ // two labels on this card
						method: 'PUT',
						url: `${api}/boards/${boardId}/stacks/${todoId}/cards/${card.id}/assignLabel`,
						auth,
						body: { labelId: bug.id },
					}).then(() => cy.request({
						method: 'PUT',
						url: `${api}/boards/${boardId}/stacks/${todoId}/cards/${card.id}/assignLabel`,
						auth,
						body: { labelId: backend.id },
					}))
				).then(() =>
					mk('Ship feature').then((card) =>
						cy.request({
							method: 'PUT',
							url: `${api}/boards/${boardId}/stacks/${todoId}/cards/${card.id}/assignLabel`,
							auth,
							body: { labelId: feature.id },
						})
					)
				).then(() => mk('Unlabeled work'))
					.then(() => cy.wrap({ boardId }))
			)
		})
	})
}

describe('Swimlane grouping', function() {
	let boardId

	before(function() {
		cy.createUser(user)
		cy.login(user)
		seedSwimlaneBoard().then((ctx) => { boardId = ctx.boardId })
	})

	beforeEach(function() {
		cy.login(user)
		cy.visit(`/apps/deck/board/${boardId}`)
		cy.get('.stack', { timeout: 10000 }).should('have.length', 3)
	})

	afterEach(function() {
		// Reset the board to flat view between tests so each test is independent
		cy.request({
			method: 'POST',
			url: `${Cypress.env('baseUrl')}/index.php/apps/deck/api/v1.0/config/board:${boardId}:swimlaneMode`,
			auth: { user: user.userId, password: user.password },
			body: { value: 'none' },
		})
	})

	it('flat view shows no swimlanes initially', function() {
		cy.get('.swimlane').should('not.exist')
		cy.get('.card').should('have.length.at.least', 3)
	})

	it('groups cards by labels', function() {
		cy.get('button[aria-label="View Modes"]').first().click()
		cy.contains('Group by labels').click()

		cy.get('.swimlane', { timeout: 8000 }).should('have.length.at.least', 4)
		cy.get('.swimlane-header__title, .swimlane-header__label').should('contain.text', 'Bug')
		cy.get('.swimlane-header__title, .swimlane-header__label').should('contain.text', 'Feature')
		cy.get('.swimlane-header__title, .swimlane-header__label').should('contain.text', 'Backend')
		cy.get('.swimlane-header__title, .swimlane-header__label').last().should('contain.text', 'No label')
	})

	it('renders a multi-label card in every matching lane', function() {
		cy.get('button[aria-label="View Modes"]').first().click()
		cy.contains('Group by labels').click()
		cy.get('.swimlane', { timeout: 8000 }).should('have.length.at.least', 4)

		// "Fix login bug" has Bug + Backend labels \u2014 must appear in both lanes
		const titleXpath = (lane) => `.swimlane:has(.swimlane-header:contains("${lane}")) .card:contains("Fix login bug")`
		cy.get(titleXpath('Bug')).should('exist')
		cy.get(titleXpath('Backend')).should('exist')
	})

	it('groups cards by assignees with Unassigned catchall last', function() {
		cy.get('button[aria-label="View Modes"]').first().click()
		cy.contains('Group by assignees').click()

		cy.get('.swimlane', { timeout: 8000 }).should('have.length.at.least', 1)
		cy.get('.swimlane-header__title').last().should('contain.text', 'Unassigned')
	})

	it('returns to flat view when No grouping is selected', function() {
		cy.get('button[aria-label="View Modes"]').first().click()
		cy.contains('Group by labels').click()
		cy.get('.swimlane', { timeout: 8000 }).should('exist')

		cy.get('button[aria-label="View Modes"]').first().click()
		cy.contains('No grouping').click()

		cy.get('.swimlane').should('not.exist')
		cy.get('.stack').should('have.length', 3)
	})

	it('persists the grouping mode across reload', function() {
		cy.get('button[aria-label="View Modes"]').first().click()
		cy.contains('Group by labels').click()
		cy.get('.swimlane', { timeout: 8000 }).should('exist')

		cy.reload()
		cy.get('.swimlane', { timeout: 10000 }).should('have.length.at.least', 4)
	})
})
