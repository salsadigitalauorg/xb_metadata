<?php

/**
 * @file
 * Alert related functions.
 */

declare(strict_types=1);

/**
 * Page pre-process to support showing of alerts.
 */
function _civictheme_preprocess_page__civictheme_alert(array &$variables): void {
  $variables['attributes']['data-component-name'] = 'ct-alerts';
  // @fixme see issue #3472691.
  //$variables['attributes']['data-alert-endpoint'] = '/api/civictheme-alerts?_format=json';
}
