<?php

declare(strict_types=1);

namespace Drupal\Tests\experience_builder\Kernel\Config;

use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\experience_builder\Entity\ContentTemplate;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\Tests\node\Traits\ContentTypeCreationTrait;

/**
 * Tests the component tree aspects of the ContentTemplate config entity type.
 *
 * @group experience_builder
 * @coversDefaultClass \Drupal\experience_builder\Entity\PageRegion
 */
final class ContentTemplateComponentTreeTest extends ConfigWithComponentTreeTestBase {

  use ContentTypeCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'field',
    'node',
  ];

  /**
   * {@inheritdoc}
   */
  protected static $expectedViolations = [
    'component_tree' => "The 'dynamic' prop source type must be present.",
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('node');
    $this->installConfig('node');
    $this->installConfig('experience_builder');
    $this->createContentType(['type' => 'alpha']);
    FieldStorageConfig::create([
      'field_name' => 'field_test',
      'type' => 'text',
      'entity_type' => 'node',
      'cardinality' => FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED,
    ])->save();
    FieldConfig::create([
      'field_name' => 'field_test',
      'entity_type' => 'node',
      'bundle' => 'alpha',
      'label' => 'Test field',
    ])->save();
    $this->generateComponentConfig();
    $this->entity = ContentTemplate::create([
      'content_entity_type_id' => 'node',
      'content_entity_type_bundle' => 'alpha',
      'content_entity_type_view_mode' => 'full',
      'component_tree' => [],
    ]);
  }

}
