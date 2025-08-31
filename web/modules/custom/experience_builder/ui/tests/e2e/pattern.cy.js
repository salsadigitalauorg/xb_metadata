describe('Can save and load patterns', () => {
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

  it(
    'Can create and add pattern',
    // This test is as flaky as a croissant, give it 3 goes before failing the
    // whole build to save the DA some 💰️.
    { retries: { openMode: 0, runMode: 3 } },
    () => {
      cy.viewport(2000, 1320);
      cy.loadURLandWaitForXBLoaded();
      cy.get('.xb--viewport-overlay')
        .findByLabelText('Two Column')
        .realClick({ position: 'bottomRight' });
      cy.log(
        'Save the entire node 1 layout as a pattern, so it can be added to a different node.',
      );

      // First remove the two image components because they will otherwise crash
      // due to the test not creating them in a way that allows the media entity
      // to be found based on filename.
      cy.get(
        '.xb--viewport-overlay [data-xb-component-id="sdc.xb_test_sdc.image"]',
      )
        .first()
        .trigger('contextmenu');
      cy.findByText('Delete').click();
      cy.get(
        '.xb--viewport-overlay [data-xb-component-id="sdc.xb_test_sdc.image"]',
      )
        .first()
        .trigger('contextmenu');
      cy.findByText('Delete').click();
      cy.waitForComponentNotInPreview('Image');

      cy.clickOptionInContextMenuInLayers('Two Column', 0, 'Create pattern');

      const patternName = 'The Entire Node 1';
      cy.findByLabelText('Pattern name').should(
        'have.value',
        'Two Column pattern',
      );

      cy.findByLabelText('Pattern name').click();
      cy.findByLabelText('Pattern name').clear();
      cy.findByLabelText('Pattern name').should('have.value', '');
      cy.findByLabelText('Pattern name').type(`${patternName}`);
      cy.findByLabelText('Pattern name').should('have.value', patternName);

      cy.findByText('Add to library').click();
      // The dialog should close
      cy.findByLabelText('Pattern name').should('not.exist');

      cy.openLibraryPanel();
      cy.get('.primaryPanelContent').within(() => {
        cy.findByText('Patterns').click();
        cy.findByText(patternName).should('exist');
      });
      cy.get('.primaryPanelContent')
        .as('panel')
        .should('contain.text', patternName);

      cy.loadURLandWaitForXBLoaded({ url: 'xb/node/2' });
      cy.get('#edit-title-0-value').should('exist');
      cy.waitForElementContentNotInIframe('div', 'There goes my hero');
      cy.openLibraryPanel();

      cy.get('[data-xb-component-id="sdc.xb_test_sdc.my-hero"]').should(
        'exist',
      );

      cy.get('[data-xb-component-id="sdc.xb_test_sdc.my-hero"]').realClick();
      cy.waitForElementContentInIframe('div', 'There goes my hero');

      // There should be one Hero added.
      cy.get(
        '.xb--viewport-overlay [data-xb-component-id="sdc.xb_test_sdc.my-hero"]',
      ).should('have.length', 1);

      // Add the pattern that was created earlier in this test.
      cy.findByText('Patterns').click();

      cy.get('.primaryPanelContent').within(() => {
        cy.findByText(patternName).should('exist');
        cy.findByText(patternName).click();
      });

      // After adding the pattern, there should be four Hero components.
      cy.get(
        '.xb--viewport-overlay [data-xb-component-id="sdc.xb_test_sdc.my-hero"]',
        { timeout: 10000 },
      ).should('have.length', 4);

      // The Two Column component that is the top level element of the pattern
      // should be the currently selected layer.
      cy.openLayersPanel();
      cy.findByTestId('xb-primary-panel').within(() => {
        cy.findAllByText('Two Column').should('have.length', 1);
        cy.findAllByLabelText('Two Column').should(
          'have.attr',
          'data-xb-selected',
          'true',
        );
      });
    },
  );
});
