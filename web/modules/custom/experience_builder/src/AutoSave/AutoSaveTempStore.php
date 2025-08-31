<?php

declare(strict_types=1);

namespace Drupal\experience_builder\AutoSave;

use Drupal\Core\TempStore\SharedTempStore;

/**
 * Defines an extension of SharedTempStore for auto-save purposes.
 *
 * The underlying key-value expirable storage of a shared temp store has a
 * ::getAll method but it isn't part of the SharedTempStore public API. We
 * extend SharedTempStore to expose the ability to call this method.
 *
 * @see \Drupal\experience_builder\AutoSave\AutoSaveManager::getAllAutoSaveList
 */
final class AutoSaveTempStore extends SharedTempStore {

  /**
   * Gets all auto-saved items from storage.
   *
   * @return array<string, object{data: array{data: array, entity_type: string, entity_id: string|int, label: string, langcode: ?string}, updated: int, owner: string}>
   *   All saved items.
   */
  public function getAll(): array {
    return $this->storage->getAll();
  }

  /**
   * Deletes all auto-saved items from storage.
   */
  public function deleteAll(): void {
    $this->storage->deleteAll();
  }

}
