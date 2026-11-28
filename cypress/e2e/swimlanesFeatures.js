/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
import { randUser } from '../utils/index.js'

const user = randUser()

// Cypress.env() throws when allowCypressEnv is false, which this project
// sets, and Cypress.expose() needs an expose entry that does not exist
// here. The configured base URL carries an /index.php suffix in CI, so
// strip it the way cypress/support/commands.js does.
const baseUrl = Cypress.config('baseUrl').replace(/\/index.php\/?$/g, '')

/**
 * Builds a board with three stacks, three labels and three cards. One card
 * has two labels, so it should render in two lanes when grouped by labels.
 */
function seedSwimlaneBoard() {
	const auth = { user: user.userId, password: user.password }
	const api = `${baseUrl}/index.php/apps/deck/api/v1.0`

	return cy.request({
		method: 'POST',
		url: `${api}/boards`,
		auth,
		body: { title: 'Swimlanes', color: '00ff00' },
	}).then(({ body: board }) => {
		const boardId = board.id

		const stackReq = (title, order) => cy.request({
			method: 'POST',
			url: `${api}/boards/${boardId}/stacks`,
			auth,
			body: { title, order },
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
		return stackReq('Todo', 0).then(({ body }) => {
			ids.todo = body.id
		}).then(() => stackReq('Doing', 1))
			.then(() => stackReq('Done', 2))
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
				// CardApiController::create still carries NoCSRFRequired as a
				// doc comment rather than a PHP attribute, and Nextcloud no
				// longer honours the comment form. Once an earlier request has
				// established a session, the server drops the user on this route
				// and CardService::create receives a null owner, which surfaces
				// as a 500. Clearing the cookies first leaves basic auth as the
				// only credential, which the route does accept.
				const mk = (title) => cy.clearCookies().then(() => cy.request({
					method: 'POST',
					url: `${api}/boards/${boardId}/stacks/${ids.todo}/cards`,
					auth,
					body: { title, description: '' },
				})).then(({ body }) => body)
				const assignLabel = (cardId, labelId) => cy.clearCookies().then(() => cy.request({
					method: 'PUT',
					url: `${api}/boards/${boardId}/stacks/${ids.todo}/cards/${cardId}/assignLabel`,
					auth,
					body: { labelId },
				}))
				return mk('Fix login bug')
					.then((card) => {
						// two labels on this card
						return assignLabel(card.id, ids.bug).then(() => assignLabel(card.id, ids.backend))
					})
					.then(() => mk('Ship feature').then((card) => assignLabel(card.id, ids.feature)))
					.then(() => mk('Unlabeled work'))
					.then(() => cy.wrap({ boardId, todoId: ids.todo, featureId: ids.feature }))
			})
	})
}

const viewer = randUser()

describe('Swimlane grouping, editor-only restriction', function() {
	const owner = randUser()
	let restrictedBoardId

	before(function() {
		cy.createUser(owner)
		cy.createUser(viewer)
		const ownerAuth = { user: owner.userId, password: owner.password }
		const base = `${baseUrl}/index.php/apps/deck/api/v1.0`
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
		// overridden by a logged-in session left over from cy.createUser
		// (which authenticates as admin and would make the request succeed
		// as admin, masking the permission check). The board was shared with
		// the viewer via a cy.request to the ACL API in the before() hook.
		cy.clearCookies()
		cy.request({
			method: 'POST',
			failOnStatusCode: false,
			url: `${baseUrl}/ocs/v2.php/apps/deck/api/v1.0/config/board:${restrictedBoardId}:swimlaneMode?format=json`,
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
	let todoId
	let featureId

	before(function() {
		cy.createUser(user)
		// No cy.login here. A session cookie makes Nextcloud prefer the
		// session over the basic auth that seedSwimlaneBoard sends, and the
		// Deck API then wants a CSRF token that cy.request has no way to
		// supply, so board creation would arrive without a user. beforeEach
		// logs in before every test anyway.
		seedSwimlaneBoard().then((ctx) => {
			boardId = ctx.boardId
			todoId = ctx.todoId
			featureId = ctx.featureId
		})
	})

	beforeEach(function() {
		// Reset the board to flat view and clear the lane order before each
		// test so the tests are independent (done here rather than in
		// afterEach so that a config request still in flight when the
		// previous test ends cannot undo it)
		cy.request({
			method: 'POST',
			url: `${baseUrl}/ocs/v2.php/apps/deck/api/v1.0/config/board:${boardId}:swimlaneMode?format=json`,
			auth: { user: user.userId, password: user.password },
			headers: { 'OCS-APIRequest': 'true' },
			body: { value: 'none' },
		})
		cy.request({
			method: 'POST',
			url: `${baseUrl}/ocs/v2.php/apps/deck/api/v1.0/config/board:${boardId}:swimlaneLabelOrder?format=json`,
			auth: { user: user.userId, password: user.password },
			headers: { 'OCS-APIRequest': 'true' },
			body: { value: '[]' },
		})
		cy.request({
			method: 'POST',
			url: `${baseUrl}/ocs/v2.php/apps/deck/api/v1.0/config/board:${boardId}:swimlaneUserOrder?format=json`,
			auth: { user: user.userId, password: user.password },
			headers: { 'OCS-APIRequest': 'true' },
			body: { value: '[]' },
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
		cy.contains('.action-radio__text', label).click({ force: true })
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

		// "Fix login bug" has Bug + Backend labels, so it must appear in both lanes.
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

	it('shows an "in N lanes" duplicate badge on multi-label cards only', function() {
		setModeViaMenu('Labels')
		cy.get('.swimlane', { timeout: 8000 }).should('have.length.at.least', 4)

		// "Fix login bug" (Bug + Backend) must carry the "in 2 lanes" badge so it
		// reads as one card duplicated across lanes, not two separate cards.
		// This guards the regression where the badge silently never rendered
		// because CardItem's hasBadges gate excluded label-only cards.
		cy.contains('.swimlane-header', 'Bug')
			.parents('.swimlane')
			.find('.card').contains('Fix login bug')
			.parents('.card')
			.find('.icon-badge--lanes')
			.should('contain.text', '2')

		// A single-label card ("Ship feature", Feature only) must NOT show it.
		cy.contains('.swimlane-header', 'Feature')
			.parents('.swimlane')
			.find('.card').contains('Ship feature')
			.parents('.card')
			.find('.icon-badge--lanes')
			.should('not.exist')
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

	// Cypress has no drag command, so fire the mousedown, stepped mousemove and mouseup that smooth-dnd listens for.
	const dragWithMouse = ($source, getTarget) => {
		const from = $source[0].getBoundingClientRect()
		const start = { clientX: from.left + from.width / 2, clientY: from.top + from.height / 2 }
		const opts = { scrollBehavior: false, force: true }
		cy.wrap($source).trigger('mousedown', { button: 0, ...start, ...opts })
		cy.get('body').then(() => {
			const to = getTarget()
			const steps = 10
			for (let i = 1; i <= steps; i++) {
				const point = {
					clientX: start.clientX + (to.clientX - start.clientX) * i / steps,
					clientY: start.clientY + (to.clientY - start.clientY) * i / steps,
				}
				cy.get('body').trigger('mousemove', { ...point, ...opts })
			}
			cy.get('body').trigger('mouseup', { button: 0, ...to, ...opts })
		})
	}

	// Pointer position at the horizontal middle of an element, offsetY from its top
	const pointIn = (selector, offsetY) => {
		const rect = Cypress.$(selector)[0].getBoundingClientRect()
		return { clientX: rect.left + rect.width / 2, clientY: rect.top + offsetY }
	}

	// Does not cover the ghost bug: the ghost centre lands beside the cloned card
	// containers here, so the drop works regardless. The next drag test covers it.
	it('reorders lanes via the drag handle and keeps the order after reload', function() {
		setModeViaMenu('Labels')
		cy.get('.swimlane', { timeout: 8000 }).should('have.length.at.least', 4)
		// created in this order, so ids ascend: Bug, Feature, Backend
		cy.get('.swimlane-header').eq(0).should('contain.text', 'Bug')
		cy.get('.swimlane-header').eq(1).should('contain.text', 'Feature')

		cy.intercept('POST', /\/config\/board(:|%3A)\d+(:|%3A)swimlaneLabelOrder/).as('saveLaneOrder')
		cy.get('.swimlane-header__drag-handle').eq(1).then(($handle) => {
			dragWithMouse($handle, () => pointIn('.swimlane-header:eq(0)', 2))
		})
		cy.wait('@saveLaneOrder').then(({ request, response }) => {
			expect(response.statusCode).to.eq(200)
			// Format check only. The answer is a string with the order, all three labels exist, so nothing is dropped
			// and the compact JSON the client sends is already canonical. The dropping is covered by PHPUnit and Behat.
			expect(response.body.ocs.data).to.eq(request.body.value)
		})

		cy.get('.swimlane-header').eq(0).should('contain.text', 'Feature')
		cy.get('.swimlane-header').eq(1).should('contain.text', 'Bug')
		cy.get('.swimlane-header').eq(2).should('contain.text', 'Backend')
		cy.get('.swimlane-header').last().should('contain.text', 'No label')

		cy.reload()
		cy.get('.swimlane', { timeout: 10000 }).should('have.length.at.least', 4)
		cy.get('.swimlane-header').eq(0).should('contain.text', 'Feature')
		cy.get('.swimlane-header').eq(1).should('contain.text', 'Bug')
	})

	it('drops a dragged lane below another lane although the ghost centre sits over a card container', function() {
		setModeViaMenu('Labels')
		cy.get('.swimlane', { timeout: 8000 }).should('have.length.at.least', 4)
		cy.get('.swimlane-header').eq(0).should('contain.text', 'Bug')
		cy.get('.swimlane-header').eq(1).should('contain.text', 'Feature')

		cy.intercept('POST', /\/config\/board(:|%3A)\d+(:|%3A)swimlaneLabelOrder/).as('saveLaneOrder')
		cy.get('.swimlane-header__drag-handle').eq(0).then(($handle) => {
			const lane = Cypress.$('.swimlane')[0].getBoundingClientRect()
			const handle = $handle[0].getBoundingClientRect()
			const board = Cypress.$('.board--swimlanes')[0].getBoundingClientRect()
			expect(lane.bottom, 'lane fully visible').to.be.at.most(board.bottom)
			const handleCentre = { x: handle.left + handle.width / 2, y: handle.top + handle.height / 2 }
			const laneCentre = { x: lane.left + lane.width / 2, y: lane.top + lane.height / 2 }

			// smooth-dnd picks the drop container with elementFromPoint at the
			// centre of the ghost, and the ghost is a clone of the lane. The
			// library CSS makes cloned card containers clickable, so the drag
			// only works if that centre is over a card container of the lane.
			const hit = $handle[0].ownerDocument.elementFromPoint(laneCentre.x, laneCentre.y)
			const overCardContainer = Cypress.$('.swimlane').eq(0).find('.smooth-dnd-container').toArray()
				.some((container) => container.contains(hit))
			expect(overCardContainer, 'lane centre is over a card container').to.eq(true)

			// The ghost centre keeps a fixed offset to the pointer, so aim the
			// pointer such that the ghost centre ends in the lower half of the
			// second lane, which means "drop after it"
			const offset = { x: laneCentre.x - handleCentre.x, y: laneCentre.y - handleCentre.y }
			dragWithMouse($handle, () => {
				const second = Cypress.$('.swimlane')[1].getBoundingClientRect()
				return {
					clientX: second.left + second.width / 2 - offset.x,
					clientY: second.top + second.height * 0.75 - offset.y,
				}
			})
		})
		cy.wait('@saveLaneOrder').its('response.statusCode').should('eq', 200)

		cy.get('.swimlane-header').eq(0).should('contain.text', 'Feature')
		cy.get('.swimlane-header').eq(1).should('contain.text', 'Bug')
		cy.get('.swimlane-header').eq(2).should('contain.text', 'Backend')

		cy.reload()
		cy.get('.swimlane', { timeout: 10000 }).should('have.length.at.least', 4)
		cy.get('.swimlane-header').eq(0).should('contain.text', 'Feature')
		cy.get('.swimlane-header').eq(1).should('contain.text', 'Bug')
	})

	it('keeps the last column visible in a narrow viewport', function() {
		cy.viewport(720, 900)
		setModeViaMenu('Labels')
		cy.get('.swimlane', { timeout: 8000 }).should('have.length.at.least', 4)
		cy.get('.board--swimlanes').scrollTo('right', { ensureScrollable: false })

		const headerLeft = () => {
			const headers = Cypress.$('.board__stack-headers .stack-draggable-wrapper')
			return headers[headers.length - 1].getBoundingClientRect().left
		}
		cy.get('.swimlane').each(($lane) => {
			const stacks = $lane.find('.swimlane__stack')
			// collapsed lanes render no columns
			if (stacks.length === 0) {
				return
			}
			const last = stacks[stacks.length - 1].getBoundingClientRect()
			const wrapper = $lane.closest('.smooth-dnd-draggable-wrapper')[0].getBoundingClientRect()
			// not clipped by the overflow: hidden wrapper smooth-dnd puts around each lane
			expect(last.right, 'last column right edge').to.be.at.most(wrapper.right + 1)
			// aligned with the header row
			expect(last.left, 'last column left edge').to.be.closeTo(headerLeft(), 1)
		})
	})

	// Unbreakable titles (card or stack) must not widen header or lane columns.
	// What a test creates is removed in afterEach, so the other tests keep the
	// seeded data only. Cookies are cleared first, see seedSwimlaneBoard.
	describe('unbreakable titles', function() {
		const api = `${baseUrl}/index.php/apps/deck/api/v1.0`
		const auth = () => ({ user: user.userId, password: user.password })
		let cleanupUrls = []

		afterEach(function() {
			const urls = cleanupUrls
			cleanupUrls = []
			if (urls.length === 0) {
				return
			}
			cy.clearCookies()
			for (let i = 0; i < urls.length; i++) {
				cy.request({ method: 'DELETE', url: urls[i], auth: auth() })
			}
		})

		const openLaneView = () => {
			cy.login(user)
			cy.viewport(720, 900)
			cy.visit(`/apps/deck/board/${boardId}`)
			cy.get('.stack', { timeout: 10000 }).should('have.length.at.least', 3)
			setModeViaMenu('Labels')
			cy.get('.swimlane', { timeout: 8000 }).should('have.length.at.least', 4)
			cy.get('.board--swimlanes').scrollTo('right', { ensureScrollable: false })
		}

		// Header column i and lane column i start at the same x, in every lane
		const expectColumnsAligned = () => {
			cy.get('.swimlane').each(($lane) => {
				const stacks = $lane.find('.swimlane__stack')
				// collapsed lanes render no columns
				if (stacks.length === 0) {
					return
				}
				const headers = Cypress.$('.board__stack-headers .stack-draggable-wrapper')
				expect(stacks.length, 'columns per lane').to.equal(headers.length)
				for (let i = 0; i < stacks.length; i++) {
					expect(stacks[i].getBoundingClientRect().left, 'column ' + i + ' left edge')
						.to.be.closeTo(headers[i].getBoundingClientRect().left, 1)
				}
				const last = stacks[stacks.length - 1].getBoundingClientRect()
				const wrapper = $lane.closest('.smooth-dnd-draggable-wrapper')[0].getBoundingClientRect()
				expect(last.right, 'last column right edge').to.be.at.most(wrapper.right + 1)
			})
		}

		it('keeps lane columns aligned with the header row when a card title is unbreakable', function() {
			const title = 'W'.repeat(80)
			cy.clearCookies()
			cy.request({
				method: 'POST',
				url: `${api}/boards/${boardId}/stacks/${todoId}/cards`,
				auth: auth(),
				body: { title, description: '' },
			}).then(({ body: card }) => {
				cleanupUrls.push(`${api}/boards/${boardId}/stacks/${todoId}/cards/${card.id}`)
				cy.clearCookies()
				cy.request({
					method: 'PUT',
					url: `${api}/boards/${boardId}/stacks/${todoId}/cards/${card.id}/assignLabel`,
					auth: auth(),
					body: { labelId: featureId },
				})
			})

			openLaneView()
			cy.contains('.swimlane .card', title).should('exist')
			expectColumnsAligned()

			// The card stays inside its column and the column does not scroll sideways
			cy.get('.swimlane').each(($lane) => {
				$lane.find('.card').each((_, card) => {
					const column = card.closest('.swimlane__stack')
					expect(card.getBoundingClientRect().right, 'card right edge')
						.to.be.at.most(column.getBoundingClientRect().right + 1)
				})
				$lane.find('.swimlane__stack .smooth-dnd-container.vertical').each((_, container) => {
					expect(container.scrollWidth, 'card container scrollWidth')
						.to.be.at.most(container.clientWidth)
				})
			})
		})

		it('keeps lane columns aligned with the header row when a stack title is unbreakable', function() {
			cy.clearCookies()
			cy.request({
				method: 'POST',
				url: `${api}/boards/${boardId}/stacks`,
				auth: auth(),
				body: { title: 'S'.repeat(40), order: 3 },
			}).then(({ body: stack }) => {
				cleanupUrls.push(`${api}/boards/${boardId}/stacks/${stack.id}`)
			})

			openLaneView()
			cy.get('.board__stack-headers .stack-draggable-wrapper').should('have.length', 4)
			expectColumnsAligned()
		})
	})

	// Keep this the last test of this suite, it changes the card's stack for good.
	// Mocha runs the nested suite above after it, its tests do not check card positions.
	it('moves a card between stacks inside a lane', function() {
		setModeViaMenu('Labels')
		cy.get('.swimlane', { timeout: 8000 }).should('have.length.at.least', 4)

		const featureLane = () => cy.contains('.swimlane-header', 'Feature').parents('.swimlane')
		featureLane().find('.swimlane__stack').eq(0).should('contain.text', 'Ship feature')
		featureLane().find('.swimlane__stack').eq(1).should('not.contain.text', 'Ship feature')

		cy.intercept('PUT', '**/cards/*/reorder').as('reorderCard')
		featureLane().find('.swimlane__stack').eq(0).contains('.card', 'Ship feature').then(($card) => {
			dragWithMouse($card, () => {
				const stack = $card.closest('.swimlane').find('.swimlane__stack')[1].getBoundingClientRect()
				return { clientX: stack.left + stack.width / 2, clientY: stack.top + 20 }
			})
		})
		cy.wait('@reorderCard').its('response.statusCode').should('eq', 200)

		featureLane().find('.swimlane__stack').eq(1).should('contain.text', 'Ship feature')
		featureLane().find('.swimlane__stack').eq(0).should('not.contain.text', 'Ship feature')

		cy.reload()
		cy.get('.swimlane', { timeout: 10000 }).should('have.length.at.least', 4)
		featureLane().find('.swimlane__stack').eq(1).should('contain.text', 'Ship feature')
	})
})
