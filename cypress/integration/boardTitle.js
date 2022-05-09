describe('Files default view', function() {
	beforeEach(function() {
		cy.login('admin', 'admin')
	})

	it('See the board title', function() {
		cy.get('.board-title h2')
        .should('have.length', 1)
        .first()
        .should('have.text', 'Upcoming cards')
	})
})
