/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { randUser } from '../utils/index.js'

const user = randUser()
const auth = { username: user.userId, password: user.password }
const tag = 'nextcloud-deck-board'
let firstBoard
let secondBoard
let activeCard
let archivedCard

function createBoard(title, titles) {
	return cy.createExampleBoard({
		user,
		board: {
			title,
			stacks: [{ title: 'Embedded list', cards: titles.map((title) => ({ title })) }],
		},
	})
}

function visitHost(entry = 'deck-boardComponent') {
	// Reuse Deck's authenticated page and integrations, but never run deck-main.
	// Optional attachment viewers are outside these board lifecycle tests.
	for (const plugin of ['text-viewer.mjs', 'files_pdfviewer-main.js']) {
		cy.intercept('GET', `**/${plugin}*`, {
			statusCode: 200,
			headers: { 'content-type': 'application/javascript' },
			body: '',
		})
	}
	cy.intercept('GET', '**/deck-main.js*', (request) => {
		request.url = request.url.replace('deck-main.js', `${entry}.js`)
		request.continue((response) => {
			response.headers['cache-control'] = 'no-store'
		})
	}).as('hostEntry')
	cy.visit('/apps/deck')
	cy.wait('@hostEntry').its('response.statusCode').should('eq', 200)
	cy.get('#content').then(($content) => {
		const host = $content[0].ownerDocument.createElement('section')
		host.id = 'embedded-host'
		host.style.cssText = 'height: 600px; width: 100%; overflow: auto'
		$content[0].replaceChildren(host)
	})
	cy.url().as('hostUrl')
}

function addBoard(boardId = firstBoard.id, cardId = null, id = 'embedded-board') {
	cy.window().then((win) => win.customElements.whenDefined(tag))
	return cy.document().then((document) => {
		const element = document.createElement(tag)
		element.id = id
		element.style.cssText = 'display: block; height: 500px'
		if (boardId !== null) {
			element.setAttribute('board-id', String(boardId))
		}
		if (cardId !== null) {
			element.setAttribute('card-id', String(cardId))
		}
		element.addEventListener('deck-ready', cy.stub().as(`${id}:ready`))
		element.addEventListener('deck-error', cy.stub().as(`${id}:error`))
		element.addEventListener('deck-navigation', cy.stub().as(`${id}:navigation`))
		document.querySelector('#embedded-host').append(element)
	})
}

function expectReady(id = 'embedded-board', boardId = firstBoard.id) {
	cy.get(`@${id}:ready`).should('have.been.called')
		.its('lastCall.args.0.detail').should('deep.equal', { boardId })
	cy.get(`#${id} [role="status"]`).should('not.exist')
}

function expectHostUrl() {
	cy.get('@hostUrl').then((url) => cy.url().should('eq', url))
}

function expectCardDetails(title) {
	cy.get('.modal-container .app-sidebar').should('be.visible').and('contain', title)
	cy.window().should((win) => {
		expect(win._nc_focus_trap?.some((trap) => trap.active)).to.eq(true)
	})
}

function closeCard() {
	cy.get('#embedded-board').then(($element) => {
		$element[0].ownerDocument.activeElement?.blur()
		$element[0].removeAttribute('card-id')
	})
}

describe('Embedded Deck board', () => {
	before(() => {
		cy.createUser(user).its('status').should('eq', 200)
		cy.login(user)
		createBoard('First embedded board', ['Active embedded card', 'Archived embedded card'])
			.then((board) => {
				firstBoard = board
				return cy.request({
					url: `${Cypress.expose('baseUrl')}/ocs/v2.php/apps/deck/api/v1.0/stacks/${board.id}?format=json`,
					auth,
					headers: { 'OCS-APIRequest': 'true' },
				})
			}).then(({ body }) => {
				const cards = body.ocs.data.flatMap((stack) => stack.cards)
				activeCard = cards.find((card) => card.title === 'Active embedded card')
				archivedCard = cards.find((card) => card.title === 'Archived embedded card')
				return cy.request('/csrftoken').then(({ body }) => {
					return cy.request({
						method: 'PUT',
						url: `${Cypress.expose('baseUrl')}/index.php/apps/deck/cards/${archivedCard.id}/archive`,
						auth,
						headers: { requesttoken: body.token },
					})
				})
			}).its('status').should('eq', 200)
		createBoard('Second embedded board', ['Second board card']).then((board) => {
			secondBoard = board
		})
	})

	beforeEach(() => {
		cy.login(user)
	})

	after(() => {
		cy.deleteUser(user).its('status').should('eq', 200)
	})

	it('loads a real board in light DOM without mounting the full app or navigating the host', () => {
		visitHost()
		addBoard()
		expectReady()
		cy.get('#embedded-board .board-title').should('contain', firstBoard.title)
		cy.get('#embedded-board .card').should('contain', activeCard.title)
		cy.get('#embedded-board').then(($element) => {
			expect($element[0].shadowRoot).to.eq(null)
		})
		cy.get('#content .app-navigation').should('not.exist')
		cy.get('@embedded-board:navigation').should('not.have.been.called')
		cy.get('@embedded-board:error').should('not.have.been.called')
		expectHostUrl()
	})

	it('opens cards and tabs, emits bubbling navigation, and closes details through attributes', () => {
		visitHost()
		addBoard()
		expectReady()
		cy.document().then((document) => {
			document.querySelector('#embedded-host').addEventListener('deck-navigation', cy.stub().as('hostNavigation'))
		})
		cy.contains('#embedded-board .card', activeCard.title).click()
		expectCardDetails(activeCard.title)
		cy.get('@hostNavigation').its('lastCall.args.0.detail').should('deep.equal', {
			boardId: firstBoard.id,
			cardId: activeCard.id,
			tabId: null,
		})
		cy.get('@hostNavigation').its('lastCall.args.0.bubbles').should('eq', true)
		cy.contains('.modal-container [role="tab"]', 'Comments').click()
		cy.contains('.modal-container [role="tab"]', 'Comments')
			.should('have.attr', 'aria-selected', 'true').blur()
		cy.get('#embedded-board').then(($element) => {
			$element[0].setAttribute('card-id', String(activeCard.id))
			return new Cypress.Promise((resolve) => {
				$element[0].ownerDocument.defaultView.requestAnimationFrame(resolve)
			})
		})
		closeCard()
		cy.get('.modal-container').should('not.exist')
		cy.get('@embedded-board:ready').should('have.been.calledOnce')
		cy.get('@embedded-board:error').should('not.have.been.called')
		expectHostUrl()
	})

	it('opens initial card attributes and updates board and card attributes together', () => {
		visitHost()
		addBoard(firstBoard.id, activeCard.id)
		expectReady()
		expectCardDetails(activeCard.title)
		cy.get('#embedded-board').then(($element) => {
			$element[0].setAttribute('board-id', String(secondBoard.id))
			$element[0].removeAttribute('card-id')
		})
		expectReady('embedded-board', secondBoard.id)
		cy.get('#embedded-board .board-title').should('contain', secondBoard.title)
		cy.get('.modal-container').should('not.exist')
		cy.get('@embedded-board:error').should('not.have.been.called')
		expectHostUrl()
	})

	it('preserves the active card list when opening and closing archived card details', () => {
		visitHost()
		addBoard()
		expectReady()
		cy.get('#embedded-board').then(($element) => {
			$element[0].setAttribute('card-id', String(archivedCard.id))
		})
		expectCardDetails(archivedCard.title)
		cy.get('#embedded-board .card').should('contain', activeCard.title)
		cy.get('#embedded-board .card').should('not.contain', archivedCard.title)
		closeCard()
		cy.get('.modal-container').should('not.exist')
		cy.get('#embedded-board .card').should('contain', activeCard.title)
		cy.get('@embedded-board:error').should('not.have.been.called')
	})

	it('keeps a newer card navigation when an archived request finishes late', () => {
		visitHost()
		addBoard()
		expectReady()
		cy.intercept('GET', `**/apps/deck/stacks/${firstBoard.id}/archived`, (request) => {
			request.on('response', (response) => response.setDelay(300))
		}).as('archived')
		cy.get('#embedded-board').then(($element) => {
			$element[0].setAttribute('card-id', String(archivedCard.id))
		})
		cy.get('@archived.all').should('have.length', 1)
		cy.get('#embedded-board').then(($element) => {
			$element[0].setAttribute('card-id', String(activeCard.id))
		})
		cy.wait('@archived')
		expectCardDetails(activeCard.title)
		cy.get('#embedded-board .card').should('contain', activeCard.title)
		cy.get('@embedded-board:error').should('not.have.been.called')
	})

	it('moves an element without remounting and reconnects a removed element with its new attributes', () => {
		visitHost()
		addBoard()
		expectReady()
		cy.get('#embedded-board').then(($element) => {
			const parent = $element[0].ownerDocument.createElement('div')
			$element[0].parentNode.append(parent)
			parent.append($element[0])
		})
		cy.get('#embedded-board .card').should('contain', activeCard.title)
		cy.get('@embedded-board:ready').should('have.been.calledOnce')
		cy.get('#embedded-board').then(($element) => {
			cy.wrap($element[0]).as('detachedBoard')
			$element[0].remove()
		})
		cy.get('#embedded-board').should('not.exist')
		cy.get('@detachedBoard').then(($element) => {
			const element = $element[0]
			element.setAttribute('board-id', String(secondBoard.id))
			element.ownerDocument.querySelector('#embedded-host').append(element)
		})
		expectReady('embedded-board', secondBoard.id)
		cy.get('#embedded-board .card').should('contain', 'Second board card')
		cy.get('@embedded-board:ready').should('have.been.calledTwice')
	})

	it('isolates filtering and removal between multiple boards', () => {
		visitHost()
		addBoard()
		addBoard(secondBoard.id, null, 'second-board')
		expectReady()
		expectReady('second-board', secondBoard.id)
		cy.get('#embedded-board .board-search input').type('No matching card')
		cy.get('#embedded-board .card').should('not.exist')
		cy.get('#second-board .card').should('contain', 'Second board card')
		cy.get('#embedded-board').then(($element) => $element[0].remove())
		cy.get('#second-board .card').should('contain', 'Second board card')
		cy.get('@second-board:ready').should('have.been.calledOnce')
		cy.get('@second-board:error').should('not.have.been.called')
	})

	it('restores a deleted card only in its original board even after another board mounts', () => {
		visitHost()
		addBoard()
		expectReady()
		cy.intercept('DELETE', `**/apps/deck/cards/${activeCard.id}`).as('deleteCard')
		cy.intercept('PUT', `**/apps/deck/api/v1.0/cards/${activeCard.id}`).as('restoreCard')
		cy.contains('#embedded-board .card', activeCard.title).find('.action-item__menutoggle').click()
		cy.contains('.v-popper__popper button', 'Delete card').click()
		cy.wait('@deleteCard').its('response.statusCode').should('eq', 200)
		cy.contains('#embedded-board .card', activeCard.title).should('not.exist')
		addBoard(secondBoard.id, null, 'second-board')
		expectReady('second-board', secondBoard.id)
		cy.contains('button', 'Undo').click()
		cy.wait('@restoreCard').its('response.statusCode').should('eq', 200)
		cy.get('#embedded-board .card').should('contain', activeCard.title)
		cy.get('#second-board .card').should('contain', 'Second board card')
		cy.get('#second-board .card').should('not.contain', activeCard.title)
	})

	it('reports invalid attributes and recovers when valid attributes are supplied', () => {
		visitHost()
		addBoard('0')
		cy.get('#embedded-board [role="alert"]').should('be.visible')
		cy.get('@embedded-board:error').its('lastCall.args.0.detail')
			.should('include', { boardId: '0', status: null })
		cy.get('@embedded-board:ready').should('not.have.been.called')
		cy.get('#embedded-board').then(($element) => {
			$element[0].setAttribute('board-id', String(firstBoard.id))
		})
		expectReady()
		cy.get('#embedded-board [role="alert"]').should('not.exist')
		cy.get('#embedded-board .card').should('contain', activeCard.title)
	})

	it('reports HTTP failures with the original status', () => {
		visitHost()
		cy.intercept('GET', `**/apps/deck/api/v1.0/board/${firstBoard.id}*`, {
			statusCode: 403,
			body: { message: 'Access denied' },
		})
		addBoard()
		cy.get('#embedded-board [role="alert"]').should('be.visible')
		cy.get('@embedded-board:error').its('lastCall.args.0.detail.status').should('eq', 403)
		cy.get('@embedded-board:ready').should('not.have.been.called')
	})

	it('ignores late failures from a board replaced during loading', () => {
		visitHost()
		cy.intercept('GET', `**/apps/deck/api/v1.0/board/${firstBoard.id}*`, {
			statusCode: 403,
			body: { message: 'Late access denial' },
			delay: 300,
		}).as('oldBoard')
		addBoard()
		cy.get('@oldBoard.all').should('have.length', 1)
		cy.get('#embedded-board').then(($element) => {
			$element[0].setAttribute('board-id', String(secondBoard.id))
		})
		cy.wait('@oldBoard')
		expectReady('embedded-board', secondBoard.id)
		cy.get('#embedded-board .card').should('contain', 'Second board card')
		cy.get('@embedded-board:error').should('not.have.been.called')
	})

	it('does not emit ready or mount a board removed during startup', () => {
		visitHost()
		cy.intercept('GET', `**/apps/deck/api/v1.0/board/${firstBoard.id}*`, (request) => {
			request.on('response', (response) => response.setDelay(300))
		}).as('loadingBoard')
		addBoard()
		cy.get('@loadingBoard.all').should('have.length', 1)
		cy.get('#embedded-board').then(($element) => $element[0].remove())
		cy.wait('@loadingBoard')
		cy.get('#embedded-host .board-wrapper').should('not.exist')
		cy.get('@embedded-board:ready').should('not.have.been.called')
		cy.get('@embedded-board:error').should('not.have.been.called')
	})

	it('reports cards outside the displayed board instead of opening them', () => {
		visitHost()
		addBoard(secondBoard.id)
		expectReady('embedded-board', secondBoard.id)
		cy.get('#embedded-board').then(($element) => {
			$element[0].setAttribute('card-id', String(activeCard.id))
		})
		cy.get('#embedded-board [role="alert"]').should('be.visible')
		cy.get('@embedded-board:error').should('have.been.called')
		cy.get('.modal-container').should('not.exist')
	})

	it('keeps the existing non-interactive board reference entry point', () => {
		visitHost('deck-reference')
		cy.window().should((win) => {
			expect(win._vue_richtext_widgets['deck-board']).to.exist
		}).then((win) => {
			const host = win.document.querySelector('#embedded-host')
			const widget = win._vue_richtext_widgets['deck-board']
			const element = win.document.createElement('div')
			element.id = 'board-reference'
			host.append(element)
			widget.callback(element, {
				richObjectType: 'deck-board',
				richObject: { board: firstBoard },
				accessible: true,
				interactive: false,
			})
		})
		cy.get('#board-reference a').should('contain', firstBoard.title)
			.and('have.attr', 'href').and('include', `/board/${firstBoard.id}`)
		cy.get('#board-reference .board-wrapper').should('not.exist')
		expectHostUrl()
	})
})
