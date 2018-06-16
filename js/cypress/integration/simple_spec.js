const server = 'http://localhost:8140/index.php/apps/deck';

describe('Deck basic features', function() {

  // initial login
  before(() => {
    cy.clearCookie('nc_session_id');
    cy.visit(server);
    cy.get('input#user').type('admin');
    cy.get('input#password').type('admin');
    cy.get('#submit').click();
  });

  // keep user logged in
  beforeEach(() => {
		Cypress.Cookies.defaults({
			whitelist: [ "nc_session_id", "nc_username", "nc_token", "oc_sessionPassphrase" ]
		});
	})

  it('Open the app', function() {
    cy.server()
    cy.route('/index.php/apps/deck/*').as('apiCalls')
    cy.visit(server);
    cy.wait('@apiCalls');
    // check for create new buttons
    cy.get('#app-navigation ul').contains('Create a new board').should('be.visible');
  })

  it('Create a board', function() {
    cy.server()
    cy.route('/index.php/apps/deck/boards').as('getBoards')
    cy.route('POST', '/index.php/apps/deck/boards',).as('createBoard')
    cy.visit(server);
    cy.wait('@getBoards');
    cy.get('#app-navigation ul').contains('Create a new board').parent().within(function() {
      cy.scrollTo('center');
      cy.get('a').should('be.visible').click();
      cy.get('input[type=text]').type('Board A');
      cy.get('input[type=submit].icon-checkmark').click().wait('@createBoard');
    });

    cy.get('#boardlist').contains('Board A');
    cy.get('#app-navigation ul li').contains('Board A');
  })

  it('Open a board', function() {
    cy.server()
    cy.route('/index.php/apps/deck/*').as('apiCalls')
    cy.visit(server);

    cy.get('#app-content').contains('Board A').click();
    cy.wait('@apiCalls');
  });
})
