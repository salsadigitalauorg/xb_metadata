import { expect } from '@playwright/test';
import { test } from './fixtures/DrupalSite';
import { getModuleDir } from './utilities/DrupalFilesystem';
import { readFile } from 'fs/promises';
import { Drupal } from './objects/Drupal';
/**
 * Tests installing Experience Builder.
 */
test.describe('Menu Component', () => {
  test.beforeAll(
    'Setup test site with Experience Builder',
    async ({ browser, drupalSite }) => {
      const page = await browser.newPage();
      const drupal: Drupal = new Drupal({ page, drupalSite });
      await drupal.installModules(['experience_builder']);
      const moduleDir = await getModuleDir();
      await drupal.applyRecipe(
        `${moduleDir}/experience_builder/tests/fixtures/recipes/menu`,
      );
      // @todo remove the cache clear once https://www.drupal.org/project/drupal/issues/3534825
      // is fixed.
      await drupal.drush('cr');
      await page.close();
    },
  );

  test('Add and test menu component', async ({ page, drupal, xBEditor }) => {
    await drupal.loginAsAdmin();
    await drupal.createXbPage('Homepage', '/homepage');
    await page.goto('/homepage');
    await xBEditor.goToEditor();
    const moduleDir = await getModuleDir();

    const code = await readFile(
      `${moduleDir}/experience_builder/tests/fixtures/code_components/menus/Menu.jsx`,
      'utf-8',
    );
    await xBEditor.createCodeComponent('Menu', code);
    const preview = xBEditor.getCodePreviewFrame();

    await expect(preview).toContainText('JSON:API Menu');
    await expect(preview).toContainText('Core Linkset Menu');
    const menus = await preview.getByTestId('menu').all();
    for (const menu of menus) {
      await expect(menu.getByTestId('menu-links')).toMatchAriaSnapshot(`
        - list:
          - listitem:
            - link "Home":
              - /url: "/"
          - listitem:
            - link "Shop":
              - /url: ""
          - listitem:
            - link "Space Bears":
              - /url: ""
            - button
          - listitem:
            - link "Mars Cars":
              - /url: ""
          - listitem:
            - link "Contact":
              - /url: "/"
      `);
      await menu.getByTestId('open-submenu').click({ force: true });
      await expect(menu.getByTestId('submenu')).toBeVisible();
      await expect(menu.getByTestId('submenu')).toMatchAriaSnapshot(`
        - list:
          - listitem:
            - link "Space Bear 6":
              - /url: "/admin"
          - listitem:
            - link "Space Bear 6 Plus":
              - /url: "/admin/structure"
          - listitem:
            - link "Mega Space Bears":
              - /url: ""
      `);
      // Close the menu otherwise it will cover the menu below and cause it to be not visible.
      await menu.getByTestId('open-submenu').click({ force: true });
    }
  });
});
