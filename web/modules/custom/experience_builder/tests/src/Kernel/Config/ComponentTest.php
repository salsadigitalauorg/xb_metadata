<?php

declare(strict_types=1);

namespace Drupal\Tests\experience_builder\Kernel\Config;

use Drupal\Core\Entity\EntityListBuilderInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Theme\ComponentPluginManager as CoreComponentPluginManager;
use Drupal\experience_builder\ComponentIncompatibilityReasonRepository;
use Drupal\experience_builder\Entity\ComponentInterface;
use Drupal\experience_builder\Entity\VersionedConfigEntityInterface;
use Drupal\experience_builder\Plugin\ComponentPluginManager;
use Drupal\experience_builder\Entity\Component;
use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\experience_builder\Traits\ContribStrictConfigSchemaTestTrait;
use Drupal\Tests\experience_builder\Traits\GenerateComponentConfigTrait;

/**
 * @group experience_builder
 */
class ComponentTest extends KernelTestBase {

  use ContribStrictConfigSchemaTestTrait;
  use GenerateComponentConfigTrait;

  const MISSING_COMPONENT_ID = 'experience_builder:missing-component';
  const MISSING_CONFIG_ENTITY_ID = 'sdc.experience_builder.missing-component';
  const LABEL = 'Test Component';

  protected CoreComponentPluginManager $componentPluginManager;
  protected ComponentIncompatibilityReasonRepository $repository;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'experience_builder',
    'sdc',
    'sdc_test',
    'xb_test_sdc',
    // XB's dependencies (modules providing field types + widgets).
    'datetime',
    'file',
    'image',
    'options',
    'path',
    'link',
    'system',
    'user',
    'text',
    'filter',
    'ckeditor5',
    'editor',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->componentPluginManager = $this->container->get(ComponentPluginManager::class);
    $this->repository = $this->container->get(ComponentIncompatibilityReasonRepository::class);
    $this->installConfig('experience_builder');
  }

  protected function midTestSetUp(): void {
    // The Standard install profile's "image" media type must be installed when
    // the media_library module gets installed.
    // @see core/profiles/standard/config/optional/media.type.image.yml
    $this->enableModules(['field', 'file', 'image', 'media']);
    $this->generateComponentConfig();
    $this->setInstallProfile('standard');
    $this->container->get('config.installer')->installOptionalConfig();

    $modules = [
      'media_library',
      'views',
      'user',
      'filter',
    ];
    $this->enableModules($modules);
    $this->generateComponentConfig();
    // @see \Drupal\Core\Field\Plugin\Field\FieldType\EntityReferenceItem::generateSampleValue()
    $this->installEntitySchema('media');

    // @see \Drupal\media_library\Plugin\Field\FieldWidget\MediaLibraryWidget
    $this->installEntitySchema('user');

    // @see core/profiles/standard/config/optional/media.type.image.yml
    $this->installConfig(['media']);

    // A sample value is generated during the test, which needs this table.
    $this->installSchema('file', ['file_usage']);

    // @see \Drupal\media_library\MediaLibraryEditorOpener::__construct()
    $this->installEntitySchema('filter_format');
  }

  /**
   * @see media_library_storage_prop_shape_alter()
   * @see \Drupal\Tests\experience_builder\Kernel\MediaLibraryHookStoragePropAlterTest
   */
  public function testComponentAutoUpdate(): void {
    $this->assertEmpty(Component::loadMultiple());
    $this->componentPluginManager->getDefinitions();
    $initial_components = Component::loadMultiple();
    $this->assertNotEmpty($initial_components);

    // Originally:
    // - uses `image` field type
    // - one version
    // - depends on `image` module
    $this->assertArrayHasKey('sdc.xb_test_sdc.image', $initial_components);
    $initial_component = $initial_components['sdc.xb_test_sdc.image'];
    $this->assertSame('image', $initial_component->getSettings()['prop_field_definitions']['image']['field_type']);
    $initial_expected_version = 'd3a3df7d7e68efc0';
    self::assertSame($initial_expected_version, $initial_component->getActiveVersion());
    self::assertSame([$initial_expected_version], $initial_component->getVersions());
    self::assertSame([
      'config' => [
        'image.style.xb_parametrized_width',
      ],
      'module' => ['file', 'image', 'xb_test_sdc'],
    ], $initial_component->getDependencies());
    self::assertSame([
      'config' => [
        'image.style.xb_parametrized_width',
      ],
      'module' => ['file', 'image', 'xb_test_sdc'],
    ], $initial_component->calculateDependencies()->getDependencies());
    self::assertSame([
      'config' => [
        'image.style.xb_parametrized_width',
      ],
      'module' => ['file', 'image', 'xb_test_sdc'],
    ], $initial_component->getVersionSpecificDependencies(VersionedConfigEntityInterface::ACTIVE_VERSION));

    // Then:
    // - uses `entity_reference` field type
    // - two versions
    // - depends on both the 'image' and `media_library` module, because there
    //   are now two versions.
    $this->midTestSetUp();
    $updated_component = Component::load('sdc.xb_test_sdc.image');
    assert($updated_component instanceof Component);
    $this->assertSame('entity_reference', $updated_component->getSettings()['prop_field_definitions']['image']['field_type']);
    $updated_expected_version = 'c06e0be7dd131740';
    self::assertSame('c06e0be7dd131740', $updated_component->getActiveVersion());
    self::assertSame(['c06e0be7dd131740', 'd3a3df7d7e68efc0'], $updated_component->getVersions());
    self::assertSame([
      'config' => [
        'field.field.media.image.field_media_image',
        'image.style.xb_parametrized_width',
        'media.type.image',
      ],
      'module' => [
        'file',
        'image',
        'media',
        'media_library',
        'xb_test_sdc',
      ],
    ], $updated_component->getDependencies());
    self::assertSame([
      'config' => [
        'image.style.xb_parametrized_width',
      ],
      'module' => ['file', 'image', 'xb_test_sdc'],
    ], $updated_component->getVersionSpecificDependencies($initial_expected_version));
    self::assertSame([
      'config' => [
        'field.field.media.image.field_media_image',
        'image.style.xb_parametrized_width',
        'media.type.image',
      ],
      'module' => [
        'file',
        'media',
        'media_library',
        'xb_test_sdc',
      ],
    ], $updated_component->getVersionSpecificDependencies(VersionedConfigEntityInterface::ACTIVE_VERSION));

    // Now specifically load the old version, and check that calling
    // ::calculateDependencies() again causes ::getDependencies() to return only
    // the dependencies of THAT version. ⚠️
    self::assertTrue($updated_component->isLoadedVersionActiveVersion());
    $updated_component->loadVersion('d3a3df7d7e68efc0');
    self::assertFalse($updated_component->isLoadedVersionActiveVersion());
    $this->assertSame('image', $updated_component->getSettings()['prop_field_definitions']['image']['field_type']);
    self::assertSame([
      'config' => [
        'field.field.media.image.field_media_image',
        'image.style.xb_parametrized_width',
        'media.type.image',
      ],
      'module' => [
        'file',
        'image',
        'media',
        'media_library',
        'xb_test_sdc',
      ],
    ], $updated_component->getDependencies());
    self::assertSame([
      'config' => [
        'field.field.media.image.field_media_image',
        'image.style.xb_parametrized_width',
        'media.type.image',
      ],
      'module' => [
        'file',
        'image',
        'media',
        'media_library',
        'xb_test_sdc',
      ],
    ], $updated_component->calculateDependencies()->getDependencies());
    $updated_component->loadVersion('c06e0be7dd131740');
    self::assertTrue($updated_component->isLoadedVersionActiveVersion());

    // Finally, because no component instances exist that use the old version,
    // the old version can be deleted, and then:
    // - uses `entity_reference`
    // - one version
    // - depends on the `media_library` module
    $updated_component->deleteVersion($initial_expected_version)->save();
    $component_without_obsolete_versions = Component::load('sdc.xb_test_sdc.image');
    assert($component_without_obsolete_versions instanceof Component);
    $this->assertSame('entity_reference', $updated_component->getSettings()['prop_field_definitions']['image']['field_type']);
    self::assertSame($updated_expected_version, $updated_component->getActiveVersion());
    self::assertSame([$updated_expected_version], $updated_component->getVersions());
    self::assertSame([
      'config' => [
        'field.field.media.image.field_media_image',
        'image.style.xb_parametrized_width',
        'media.type.image',
      ],
      'module' => ['file', 'media', 'media_library', 'xb_test_sdc'],
    ], $updated_component->getDependencies());
  }

  public function testOperations(): void {
    $this->installConfig('experience_builder');
    $list_builder = $this->container->get(EntityTypeManagerInterface::class)->getListBuilder(Component::ENTITY_TYPE_ID);
    \assert($list_builder instanceof EntityListBuilderInterface);
    $this->componentPluginManager->getDefinitions();
    $component = Component::load('sdc.xb_test_sdc.image');
    \assert($component instanceof ComponentInterface);
    $operations = $list_builder->getOperations($component);
    self::assertArrayHasKey('disable', $operations);
    self::assertArrayNotHasKey('enable', $operations);
    self::assertArrayNotHasKey('delete', $operations);

    $component->disable()->save();
    $operations = $list_builder->getOperations($component);
    self::assertArrayNotHasKey('disable', $operations);
    self::assertArrayHasKey('enable', $operations);
    self::assertArrayNotHasKey('delete', $operations);
  }

}
