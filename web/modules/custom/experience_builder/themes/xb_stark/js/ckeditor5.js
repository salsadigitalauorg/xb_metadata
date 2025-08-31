/**
 * CKEditor5 dialog integration for Experience Builder.
 */

// Store a reference to the original openDialog function.
const originalOpenDialog = Drupal.ckeditor5.openDialog;

/**
 * Override for Drupal.ckeditor5.openDialog function.
 *
 * Intercepts CKEditor5 dialog creation to:
 * - Force the admin theme when inside Experience Builder
 * - Add special CSS classes for Experience Builder styling
 *
 * @param {...*} args
 *   The original arguments (url, saveCallback, dialogSettings)
 */
Drupal.ckeditor5.openDialog = function (...args) {
  let [url] = args;
  const [, saveCallback, dialogSettings] = args;
  if (document.querySelector('#experience-builder')) {
    // Add XB-specific dialog class so it is not using the very narrow default
    // width styling.
    dialogSettings.dialogClass =
      `${dialogSettings.dialogClass || ''} ck-xb-dialog`.trim();

    // Add URL arg that instructs the renderer to use the admin theme.
    url = `${url}${url.includes('?') ? '&' : '?'}use_admin_theme=1`;
    // Add dialog setting that instructs the add_css AJAX command to scope the
    // CSS within the dialog.
    dialogSettings.useAdminTheme = true;
  }
  originalOpenDialog.apply(this, [url, saveCallback, dialogSettings]);
};
