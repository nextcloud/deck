/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
import { randUser } from '../utils/index.js'

const user = randUser()

/**
 * Builds a board with three stacks, three labels, two cards \u2014 one card
 * has two labels so it should render in two lanes when grouped by labels.
 */
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

		// Cypress chainables are not promises, so the requests are chained
		// sequentially instead of via Promise.all
		const ids = {}
		return stackReq('Todo').then(({ body }) => {
			ids.todo = body.id
		}).then(() => stackReq('Doing'))
			.then(() => stackReq('Done'))
			.then(() => labelReq('Bug', 'ff0000'))
			.then((label) => {
				ids.bug = label.id
			})
			.then(() => labelReq('Feature', '00ff00'))
			.then((label) => {
				ids.feature = label.id
			})
			.then(() => labelReq('Backend', '0000ff'))
			.then((label) => {
				ids.backend = label.id
			})
			.then(() => {
				const mk = (title) => cy.request({
					method: 'POST',
					url: `${api}/boards/${boardId}/stacks/${ids.todo}/cards`,
					auth,
					body: { title, description: '' },
				}).then(({ body }) => body)
				const assignLabel = (cardId, labelId) => cy.request({
					method: 'PUT',
					url: `${api}/boards/${boardId}/stacks/${ids.todo}/cards/${cardId}/assignLabel`,
					auth,
					body: { labelId },
				})
				return mk('Fix login bug').then((card) =>
					// two labels on this card
					assignLabel(card.id, ids.bug).then(() => assignLabel(card.id, ids.backend)),
				).then(() =>
					mk('Ship feature').then((card) => assignLabel(card.id, ids.feature)),
				).then(() => mk('Unlabeled work'))
					.then(() => cy.wrap({ boardId }))
			})
	})
}

const viewer = randUser()

describe('Swimlane grouping — editor-only restriction', function() {
	const owner = randUser()
	let restrictedBoardId

	before(function() {
		cy.createUser(owner)
		cy.createUser(viewer)
		const ownerAuth = { user: owner.userId, password: owner.password }
		const base = `${Cypress.env('baseUrl')}/index.php/apps/deck/api/v1.0`
		cy.request({
			method: 'POST',
			url: `${base}/boards`,
			auth: ownerAuth,
			body: { title: 'Restricted board', color: '0000ff' },
		}).then(({ body }) => {
			restrictedBoardId = body.id
			// Share via API with all permission flags explicitly false; the
			// share dialog grants edit by default, which would invalidate this
			// "read-only" suite. PERMISSION_TYPE_USER = 0.
			cy.request({
				method: 'POST',
				url: `${base}/boards/${restrictedBoardId}/acl`,
				auth: ownerAuth,
				body: {
					type: 0,
					participant: viewer.userId,
					permissionEdit: false,
					permissionShare: false,
					permissionManage: false,
				},
			})
		})
	})

	it('read-only user cannot set swimlaneMode via the API (403)', function() {
		// Clear cookies so the basic-auth credentials below aren't silently
		// overridden by a logged-in session left over from cy.createUser /
		// cy.shareBoardWithUi (which authenticate as admin and would make
		// the request succeed as admin, masking the permission check).
		cy.clearCookies()
		cy.request({
			method: 'POST',
			failOnStatusCode: false,
			url: `${Cypress.env('baseUrl')}/ocs/v2.php/apps/deck/api/v1.0/config/board:${restrictedBoardId}:swimlaneMode?format=json`,
			auth: { user: viewer.userId, password: viewer.password },
			headers: { 'OCS-APIRequest': 'true' },
			body: { value: 'labels' },
		}).then((response) => {
			expect(response.status).to.equal(403)
		})
	})

	it('swimlane grouping controls are disabled in the UI for a read-only user', function() {
		cy.login(viewer)
		cy.visit(`/apps/deck/board/${restrictedBoardId}`)
		cy.get('button[aria-label="View Modes"]', { timeout: 10000 }).first().click()
		cy.get('input[name="swimlaneMode"]').each(($input) => {
			cy.wrap($input).should('be.disabled')
		})
	})
})

describe('Swimlane grouping', function() {
	let boardId

	before(function() {
		cy.createUser(user)
		cy.login(user)
		seedSwimlaneBoard().then((ctx) => { boardId = ctx.boardId })
	})

	beforeEach(function() {
		// Reset the board to flat view before each test so the tests are
		// independent (done here rather than in afterEach so that a config
		// request still in flight when the previous test ends cannot undo it)
		cy.request({
			method: 'POST',
			url: `${Cypress.env('baseUrl')}/ocs/v2.php/apps/deck/api/v1.0/config/board:${boardId}:swimlaneMode?format=json`,
			auth: { user: user.userId, password: user.password },
			body: { value: 'none' },
		})
		cy.login(user)
		cy.visit(`/apps/deck/board/${boardId}`)
		cy.get('.stack', { timeout: 10000 }).should('have.length', 3)
	})

	// Selects a grouping mode in the View Modes menu and waits for the
	// config request to be persisted, so the next test cannot race it
	const setModeViaMenu = (label) => {
		cy.intercept('POST', '**/apps/deck/api/v1.0/config/**').as('saveSwimlaneMode')
		cy.get('button[aria-label="View Modes"]').first().click()
		// {force: true} is needed because the NcActions menu has a brief
		// CSS visibility:hidden phase on the action-radio span while it
		// animates open, which is what an end user would see as a normal
		// click target
		cy.contains('.action-radio__label', label).click({ force: true })
		cy.wait('@saveSwimlaneMode')
	}

	it('flat view shows no swimlanes initially', function() {
		cy.get('.swimlane').should('not.exist')
		cy.get('.card').should('have.length.at.least', 3)
	})

	it('groups cards by labels', function() {
		setModeViaMenu('Labels')

		cy.get('.swimlane', { timeout: 8000 }).should('have.length.at.least', 4)
		cy.get('.swimlane-header__title, .swimlane-header__label').should('contain.text', 'Bug')
		cy.get('.swimlane-header__title, .swimlane-header__label').should('contain.text', 'Feature')
		cy.get('.swimlane-header__title, .swimlane-header__label').should('contain.text', 'Backend')
		cy.get('.swimlane-header__title, .swimlane-header__label').last().should('contain.text', 'No label')
	})

	it('renders a multi-label card in every matching lane', function() {
		setModeViaMenu('Labels')
		cy.get('.swimlane', { timeout: 8000 }).should('have.length.at.least', 4)

		// "Fix login bug" has Bug + Backend labels \u2014 must appear in both lanes.
		// Use cy.contains() to avoid embedding lane names into a CSS selector string.
		const expectCardInLane = (laneName, cardTitle) => {
			cy.contains('.swimlane-header', laneName)
				.parents('.swimlane')
				.find('.card')
				.contains(cardTitle)
				.should('exist')
		}
		expectCardInLane('Bug', 'Fix login bug')
		expectCardInLane('Backend', 'Fix login bug')
	})

	it('groups cards by assignees with Unassigned catchall last', function() {
		setModeViaMenu('Assignees')

		cy.get('.swimlane', { timeout: 8000 }).should('have.length.at.least', 1)
		cy.get('.swimlane-header__title').last().should('contain.text', 'Unassigned')
	})

	it('returns to flat view when No grouping is selected', function() {
		setModeViaMenu('Labels')
		cy.get('.swimlane', { timeout: 8000 }).should('exist')

		setModeViaMenu('No grouping')

		cy.get('.swimlane').should('not.exist')
		cy.get('.stack').should('have.length', 3)
	})

	it('persists the grouping mode across reload', function() {
		setModeViaMenu('Labels')
		cy.get('.swimlane', { timeout: 8000 }).should('exist')

		cy.reload()
		cy.get('.swimlane', { timeout: 10000 }).should('have.length.at.least', 4)

		// Radio reflects the persisted mode after reload
		cy.get('button[aria-label="View Modes"]').first().click()
		cy.get('input[name="swimlaneMode"][value="labels"]').should('be.checked')
	})
})
