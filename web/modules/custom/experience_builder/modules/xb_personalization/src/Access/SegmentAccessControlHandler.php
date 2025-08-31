<?php

declare(strict_types=1);

namespace Drupal\xb_personalization\Access;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\experience_builder\EntityHandlers\XbConfigEntityAccessControlHandler;
use Drupal\xb_personalization\Entity\Segment;
use Drupal\xb_personalization\Entity\SegmentInterface;

/**
 * Defines the access control handler for Segment entities.
 *
 * @see \Drupal\xb_personalization\Entity\Segment
 */
final class SegmentAccessControlHandler extends XbConfigEntityAccessControlHandler {

  /**
   * {@inheritdoc}
   */
  protected function checkAccess(EntityInterface $entity, $operation, AccountInterface $account): AccessResultInterface {
    assert($entity instanceof SegmentInterface);
    if (in_array($operation, ['update', 'delete'], TRUE) && $entity->id() === Segment::DEFAULT_ID) {
      return AccessResult::forbidden('The default segment cannot be deleted or updated.');
    }

    return parent::checkAccess($entity, $operation, $account);
  }

}
