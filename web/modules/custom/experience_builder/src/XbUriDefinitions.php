<?php

declare(strict_types=1);

namespace Drupal\experience_builder;

/**
 * Provides link relations for the internal HTTP API.
 *
 * @internal
 */
class XbUriDefinitions {

  const string LINK_REL_EDIT = 'edit-form';
  const string LINK_REL_DELETE = 'delete-form';
  const string LINK_REL_DUPLICATE = 'https://drupal.org/project/experience_builder#link-rel-duplicate';
  const string LINK_REL_SET_AS_HOMEPAGE = 'https://drupal.org/project/experience_builder#link-rel-set-as-homepage';

}
