describe('Undo/Redo functionality', () => {
  before(() => {
    cy.drupalXbInstall();
  });

  beforeEach(() => {
    cy.drupalSession();
    cy.drupalLogin('xbUser', 'xbUser');
  });

  after(() => {
    cy.drupalUninstall();
  });

  it('Performs a basic interaction with Undo/Redo', () => {
    cy.loadURLandWaitForXBLoaded();

    // Assert that the undo button is disabled initially.
    cy.get('button[aria-label="Undo"]').should('be.disabled');

    // Check there are three heroes initially.
    cy.testInIframe(
      '[data-component-id="xb_test_sdc:my-hero"]',
      (myHeroComponent) => {
        expect(myHeroComponent.length).to.equal(3);
      },
    );
    cy.get('.primaryPanelContent').findByText('Two Column').click();
    cy.intercept('POST', '**/xb/api/v0/layout/node/1').as('getPreview');
    cy.openLibraryPanel();

    // Click on the menu item with data-xb-name="Hero" inside menu.
    cy.get('.primaryPanelContent [data-xb-name="Hero"]').click();
    cy.wait('@getPreview');

    cy.getIframeBody().find(
      '[data-component-id="xb_test_sdc:my-hero"]',
      (myHeroComponent) => {
        expect(myHeroComponent.length).to.equal(4);
      },
    );
    // Undo
    cy.realPress(['Meta', 'Z']);
    cy.wait('@getPreview');

    // Assert that the component was deleted from the layout.
    cy.getIframeBody().find(
      '[data-component-id="xb_test_sdc:my-hero"]',
      (myHeroComponent) => {
        expect(myHeroComponent.length).to.equal(3);
      },
    );

    // Redo.
    cy.realPress(['Meta', 'Shift', 'Z']);
    cy.wait('@getPreview');

    // Assert that the component was again added to the layout.
    cy.getIframeBody().find(
      '[data-component-id="xb_test_sdc:my-hero"]',
      (myHeroComponent) => {
        expect(myHeroComponent.length).to.equal(4);
      },
    );
  });

  it('Component inputs form values are included in Undo/Redo', () => {
    cy.loadURLandWaitForXBLoaded();

    // Click on our "hello, world!" hero component.
    cy.clickComponentInPreview('Hero');

    // Add " one" to the heading field.
    cy.findByTestId(/^xb-component-form-.*/)
      .findByLabelText('Heading')
      .click();
    cy.findByTestId(/^xb-component-form-.*/)
      .findByLabelText('Heading')
      .type(' one');

    cy.waitForElementContentInIframe('.my-hero__heading', 'hello, world! one');
    cy.findByTestId(/^xb-component-form-.*/)
      .findByLabelText('Heading')
      .should('have.value', 'hello, world! one');

    // Add " two" to the heading field.
    cy.findByTestId(/^xb-component-form-.*/)
      .findByLabelText('Heading')
      .click();
    cy.findByTestId(/^xb-component-form-.*/)
      .findByLabelText('Heading')
      .type(' two');
    cy.findByTestId(/^xb-component-form-.*/)
      .findByLabelText('Heading')
      .blur();
    // Disable the no-unnecessary-waiting eslint rule below because we need to wait
    // for the debounce to finish to ensure the undo history is updated.
    cy.wait(500); // eslint-disable-line cypress/no-unnecessary-waiting

    cy.findByTestId(/^xb-component-form-.*/)
      .findByLabelText('Heading')
      .should('have.value', 'hello, world! one two');

    cy.waitForElementContentInIframe(
      '.my-hero__heading',
      'hello, world! one two',
    );

    // Undo, see if the value is "hello, world! one".
    cy.realPress(['Meta', 'Z']);
    cy.findByLabelText('Heading').should((input) => {
      expect(input).to.have.value('hello, world! one');
    });
    cy.waitForElementContentInIframe('.my-hero__heading', 'hello, world! one');

    // Redo, see if the value is "hello, world! one two".
    cy.realPress(['Meta', 'Shift', 'Z']);
    cy.findByLabelText('Heading').should((input) => {
      expect(input).to.have.value('hello, world! one two');
    });
    cy.waitForElementContentInIframe(
      '.my-hero__heading',
      'hello, world! one two',
    );

    // Undo twice, see if the value is "hello, world!".
    cy.realPress(['Meta', 'Z']);
    cy.realPress(['Meta', 'Z']);
    cy.findByLabelText('Heading').should((input) => {
      expect(input).to.have.value('hello, world!');
    });
  });
});
