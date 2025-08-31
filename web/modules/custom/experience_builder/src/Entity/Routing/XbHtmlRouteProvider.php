<?php

declare(strict_types=1);

namespace Drupal\experience_builder\Entity\Routing;

use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\Routing\DefaultHtmlRouteProvider;
use Symfony\Component\Routing\Route;

/**
 * Provides routes for entities defaulting to Experience Builder.
 *
 * Use this class if the add/edit form routes should use Experience Builder and
 * not the default entity form system.
 */
final class XbHtmlRouteProvider extends DefaultHtmlRouteProvider {

  /**
   * {@inheritdoc}
   */
  protected function getAddFormRoute(EntityTypeInterface $entity_type): ?Route {
    $route = parent::getAddFormRoute($entity_type);
    if ($route !== NULL) {
      self::removeEntityForm($route);
      $route->setDefault('_experience_builder', $entity_type->id());
    }
    return $route;
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditFormRoute(EntityTypeInterface $entity_type): ?Route {
    $route = parent::getEditFormRoute($entity_type);
    if ($route !== NULL) {
      self::removeEntityForm($route);
      $route->setDefault('_experience_builder', $entity_type->id());
    }
    return $route;
  }

  /**
   * Removes the `_entity_form` key from the route defaults.
   *
   * @param \Symfony\Component\Routing\Route $route
   *   The route to modify.
   */
  private static function removeEntityForm(Route $route): void {
    $defaults = $route->getDefaults();
    unset($defaults['_entity_form']);
    $route->setDefaults($defaults);
  }

}
