import { expect } from '@playwright/test';
import { test } from './fixtures/DrupalSite';

/**
 * Tests installing Experience Builder.
 */
test.describe('Canary XB', () => {
  test('Setup test site with Experience Builder', async ({ page, drupal }) => {
    await drupal.setupXBTestSite();
    await page.goto('/first');
    await expect(
      page
        .locator('xpath=//*[@data-component-id="xb_test_sdc:my-hero"]')
        .first(),
    ).toMatchAriaSnapshot(`
    - heading "meow!" [level=1]
    - paragraph: this is a hero
    - link "View":
      - /url: https://drupal.org
    - button "Click"
    `);
  });

  test('Rendered Pages', async ({ page }) => {
    await page.goto('/homepage');
    await expect(page.locator('css=.layout-content')).toMatchAriaSnapshot(`
      - heading "Homepage" [level=1]
      - heading "Welcome to the site!" [level=1]
      - paragraph:
        - text: Example value for
        - strong: the_body
        - text: slot in
        - strong: prop-slots
        - text: component.
      - text: Example value for <strong>the_footer</strong>.
      - link "Home":
        - /url: /
        - img "Home"
      - link "Drupal":
        - /url: /
    `);
    await page.goto('/test-page');
    await expect(page.locator('css=.layout-content')).toMatchAriaSnapshot(`
      - heading "Empty Page" [level=1]
    `);
    await page.goto('/the-one-with-a-block');
    await expect(page.locator('css=.layout-content')).toMatchAriaSnapshot(`
      - heading "XB With a block in the layout" [level=1]
      - img "A cat on top of a cat tree trying to reach a Christmas tree"
      - heading "meow!" [level=1]
      - paragraph: this is a hero
      - link "View":
        - /url: https://drupal.org
      - button "Click"
      - text: Component With props, Hello Kitty, 50 years old.
      - heading "hello, world!" [level=1]
      - paragraph: protect yourself from dangerous cats
      - link "Yes":
        - /url: https://drupal.org
      - button "No thanks"
      - text: admin
      - time: Wed, 28 May 2025 - 07:01
      - text: A Media Image Field Image
      - img "A pub called The Princes Head surrounded by trees and two red London phone boxes"
    `);
    await page.goto('/i-am-an-empty-node');
    await expect(page.locator('css=.layout-content')).toMatchAriaSnapshot(`
      - heading "I am an empty node" [level=1]
      - text: admin
      - time: /.*/
      - text: A Media Image Field Image
      - img "A pub called The Princes Head surrounded by trees and two red London phone boxes"
    `);
  });

  test('Experience Builder Layer Panel', async ({ page, drupal, xBEditor }) => {
    await drupal.loginAsAdmin();
    await page.goto('/first');
    await xBEditor.goToEditor();
    const layerPanel = 'xpath=//*[@data-testid="xb-primary-panel"]';
    const layerPanelElement = await page.locator(layerPanel);
    await expect(layerPanelElement).toContainText('Two Column');
    await expect(layerPanelElement).toContainText('Column One');
    await expect(layerPanelElement).toContainText('Column Two');
  });
});
