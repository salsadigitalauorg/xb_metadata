<?php

declare(strict_types=1);

namespace Drupal\Tests\experience_builder\Kernel;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Extension\ThemeInstallerInterface;
use Drupal\experience_builder\AutoSave\AutoSaveManager;
use Drupal\experience_builder\ClientDataToEntityConverter;
use Drupal\experience_builder\Controller\ApiLayoutController;
use Drupal\experience_builder\Entity\AssetLibrary;
use Drupal\experience_builder\Entity\JavaScriptComponent;
use Drupal\experience_builder\Entity\Page;
use Drupal\experience_builder\Entity\PageRegion;
use Drupal\experience_builder\Entity\StagedConfigUpdate;
use Drupal\experience_builder\Entity\XbHttpApiEligibleConfigEntityInterface;
use Drupal\experience_builder\Plugin\DisplayVariant\XbPageVariant;
use Drupal\experience_builder\Render\PreviewEnvelope;
use Drupal\KernelTests\KernelTestBase;
use Drupal\node\Entity\Node;
use Drupal\Tests\experience_builder\Traits\ContribStrictConfigSchemaTestTrait;
use Drupal\Tests\experience_builder\Traits\GenerateComponentConfigTrait;
use Drupal\Tests\experience_builder\Traits\XBFieldTrait;
use Drupal\Tests\media\Traits\MediaTypeCreationTrait;
use Drupal\Tests\node\Traits\ContentTypeCreationTrait;

/**
 * @coversDefaultClass \Drupal\experience_builder\AutoSave\AutoSaveManager
 * @group experience_builder.
 */
class AutoSaveManagerTest extends KernelTestBase {

  use XBFieldTrait;
  use ContribStrictConfigSchemaTestTrait;
  use GenerateComponentConfigTrait;
  use ContentTypeCreationTrait;
  use MediaTypeCreationTrait;

  private const string UUID_IN_ROOT = '78c73c1d-4988-4f9b-ad17-f7e337d40c29';

  protected static $modules = [
    'xb_test_sdc',
    'system',
    'experience_builder',
    'file',
    'image',
    'link',
    'path',
    'path_alias',
    'media',
    'user',
    'text',
    'options',
    'node',
    'filter',
    'field',
    'editor',
    'ckeditor5',
    'xb_dev_standard',
  ];

  private static function recursiveReverseSort(array $data): array {
    // If $data is associative array reverse it, but preserve the keys.
    if (!array_is_list($data)) {
      $data = array_reverse($data, TRUE);
    }
    foreach ($data as $key => $value) {
      if (is_array($value)) {
        $data[$key] = self::recursiveReverseSort($value);
      }
    }
    return $data;
  }

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->container->get(ThemeInstallerInterface::class)->install(['stark']);
    $this->config('system.theme')->set('default', 'stark')->save();
    $this->installConfig('experience_builder');
    $this->generateComponentConfig();
  }

  private function convertClientData(EntityInterface $entity, array $data): EntityInterface {
    if ($entity instanceof FieldableEntityInterface) {
      $data['model'] = (array) $data['model'];
      $layout = $data['layout'];
      $content = NULL;
      foreach ($layout as $region_node) {
        $client_side_region_id = $region_node['id'];
        if ($client_side_region_id === XbPageVariant::MAIN_CONTENT_REGION) {
          $content = $region_node;
        }
      }
      \assert($content !== NULL);
      \Drupal::service(ClientDataToEntityConverter::class)->convert(['layout' => $content] + $data, $entity, validate: FALSE);
      return $entity;
    }
    if ($entity instanceof PageRegion) {
      $entity = $entity->forAutoSaveData($data, validate: FALSE);
      return $entity;
    }
    \assert($entity instanceof XbHttpApiEligibleConfigEntityInterface);
    $updated_entity = $entity::create($entity->toArray());
    $updated_entity->updateFromClientSide($data);
    return $updated_entity;
  }

  private function assertAutoSaveCreated(EntityInterface $entity, array $matching_client_data, array $updated_client_data): void {
    $autoSave = $this->container->get(AutoSaveManager::class);
    assert($autoSave instanceof AutoSaveManager);
    $autoSaveEntity = $this->convertClientData($entity, $matching_client_data);
    $autoSave->saveEntity($autoSaveEntity);
    self::assertTrue($autoSave->getAutoSaveEntity($entity)->isEmpty());
    // Reversing the order of the data should not trigger an auto-save entry either.
    $autoSaveEntity = $this->convertClientData($entity, self::recursiveReverseSort($matching_client_data));
    $autoSave->saveEntity($autoSaveEntity);
    self::assertTrue($autoSave->getAutoSaveEntity($entity)->isEmpty());

    // Now update the entity.
    $autoSaveEntity = $this->convertClientData($entity, $updated_client_data);
    $autoSave->saveEntity($autoSaveEntity);

    self::assertFalse($autoSave->getAutoSaveEntity($entity)->isEmpty());
    $autoSaveKey = AutoSaveManager::getAutoSaveKey($entity);
    $autoSaveEntry = $autoSave->getAllAutoSaveList()[$autoSaveKey];
    self::assertArrayHasKey('data_hash', $autoSaveEntry);
    $hashInitial = $autoSaveEntry['data_hash'];
    self::assertNotEmpty($hashInitial);

    // Reversing the order of the data should result in the exact same hash.
    $autoSaveEntity = $this->convertClientData($entity, self::recursiveReverseSort($updated_client_data));
    $autoSave->saveEntity($autoSaveEntity);
    self::assertFalse($autoSave->getAutoSaveEntity($entity)->isEmpty());
    $autoSaveEntry = $autoSave->getAllAutoSaveList()[$autoSaveKey];
    self::assertArrayHasKey('data_hash', $autoSaveEntry);
    $hashReversedData = $autoSaveEntry['data_hash'];
    self::assertNotEmpty($hashReversedData);
    self::assertSame($hashInitial, $hashReversedData);

    if ($entity instanceof XbHttpApiEligibleConfigEntityInterface) {
      // Modifying the (config) entity `status` key does NOT result in the
      // auto-save being wiped, but in it being updated.
      $status_key = $entity->getEntityType()->getKey('status');
      if ($status_key) {
        self::assertTrue($autoSave->getAllAutoSaveList()[$autoSaveKey]['data'][$status_key]);
        $entity->disable()->save();
        self::assertFalse($autoSave->getAllAutoSaveList()[$autoSaveKey]['data'][$status_key]);
        // We also have to update the original client data so that a new auto
        // save entry deletes the existing (matching) data.
        $matching_client_data[$status_key] = FALSE;
      }

      // Modifying the (config) entity `label` key does NOT result in the
      // auto-save being wiped, but in it being updated.
      $label_key = $entity->getEntityType()->getKey('label');
      if ($label_key) {
        self::assertSame($updated_client_data[$label_key], $autoSave->getAllAutoSaveList()[$autoSaveKey]['data'][$label_key]);
        $entity->set($label_key, 'magic 🪄')->save();
        self::assertSame('magic 🪄', $autoSave->getAllAutoSaveList()[$autoSaveKey]['data'][$label_key]);
        // We also have to update the original client data so that a new auto
        // save entry deletes the existing (matching) data.
        $matching_client_data[$label_key] = 'magic 🪄';
      }
    }

    // Resaving the initial state should delete the auto-save entry.
    $autoSaveEntity = $this->convertClientData($entity, $matching_client_data);
    $autoSave->saveEntity($autoSaveEntity);
    self::assertTrue($autoSave->getAutoSaveEntity($entity)->isEmpty());
  }

  public function testXbPage(): void {
    $this->installEntitySchema('user');
    $this->installEntitySchema('path_alias');
    $this->installEntitySchema(Page::ENTITY_TYPE_ID);
    $xb_page = Page::create([
      'title' => '5 amazing uses for old toothbrushes',
      'components' => [],
    ]);
    self::assertCount(0, iterator_to_array($xb_page->validate()));
    self::assertSame(SAVED_NEW, $xb_page->save());

    $envelope = \Drupal::classResolver(ApiLayoutController::class)->get($xb_page);
    \assert($envelope instanceof PreviewEnvelope);
    $matching_client_data = \array_intersect_key($envelope->additionalData, \array_flip(['layout', 'model', 'entity_form_fields']));
    $new_title_client_data = $matching_client_data;
    $new_title_client_data['entity_form_fields']['title[0][value]'] = '5 MORE amazing uses for old toothbrushes';
    $this->assertAutoSaveCreated($xb_page, $matching_client_data, $new_title_client_data);

    // Confirm that adding a component triggers an auto-save entry.
    $new_component_client_data = $matching_client_data;
    $new_component_client_data['layout'][0]['components'][] = [
      'nodeType' => 'component',
      'uuid' => 'static-image-udf7d',
      // This is intentionally missing a version AND a non-existent component to
      // confirm that auto-saves do not perform validation.
      'type' => 'sdc.xb_test_sdc.static_image',
      'slots' => [],
    ];
    $this->assertAutoSaveCreated($xb_page, $matching_client_data, $new_component_client_data);
  }

  public function testPageRegion(): void {
    $page_region = PageRegion::create([
      'theme' => 'stark',
      'region' => 'sidebar_first',
      'component_tree' => [
        [
          'uuid' => self::UUID_IN_ROOT,
          'component_id' => 'sdc.xb_test_sdc.props-no-slots',
          'component_version' => '95f4f1d5ee47663b',
          'inputs' => [
            'heading' => 'world',
          ],
        ],
      ],
    ]);
    \assert($page_region instanceof PageRegion);
    $this->assertSame(SAVED_NEW, $page_region->save());
    $page_region_matching_client_data = $page_region->getComponentTree()->getClientSideRepresentation();
    $non_matching_region_client_data = $page_region_matching_client_data;
    $non_matching_region_client_data['model'][self::UUID_IN_ROOT]['resolved']['heading'] = 'This is a different heading.';
    $this->assertAutoSaveCreated($page_region, $page_region_matching_client_data, $non_matching_region_client_data);
  }

  public function testJsComponent(): void {
    $js_component = JavaScriptComponent::create([
      'machineName' => 'test',
      'name' => 'Test',
      'status' => TRUE,
      'props' => [
        'text' => [
          'type' => 'string',
          'title' => 'Title',
          'examples' => ['Press', 'Submit now'],
        ],
      ],
      'slots' => [
        'test-slot' => [
          'title' => 'test',
          'description' => 'Title',
          'examples' => [
            'Test 1',
            'Test 2',
          ],
        ],
      ],
      'js' => [
        'original' => 'console.log("Test")',
        'compiled' => 'console.log("Test")',
      ],
      'css' => [
        'original' => '.test { display: none; }',
        'compiled' => '.test{display:none;}',
      ],
    ]);
    $this->assertSame(SAVED_NEW, $js_component->save());
    $js_component_matching_client_data = $js_component->normalizeForClientSide()->values;
    $js_component_matching_client_data['importedJsComponents'] = [];
    $non_matching_js_component_client_data = $js_component_matching_client_data;
    $non_matching_js_component_client_data['props']['text']['examples'][] = 'Press, or don\'t. Whatever.';
    $this->assertAutoSaveCreated($js_component, $js_component_matching_client_data, $non_matching_js_component_client_data);
  }

  public function testAssetLibrary(): void {
    $this->installConfig('experience_builder');
    $asset_library = AssetLibrary::load('global');
    assert($asset_library instanceof AssetLibrary);
    $asset_library_matching_client_data = $asset_library->normalizeForClientSide()->values;
    $non_matching_asset_library_client_data = $asset_library_matching_client_data;
    $non_matching_asset_library_client_data['label'] = 'Slightly less boring label';
    $non_matching_asset_library_client_data['css']['original'] = $non_matching_asset_library_client_data['css']['original'] . '/**/';
    $this->assertAutoSaveCreated($asset_library, $asset_library_matching_client_data, $non_matching_asset_library_client_data);
  }

  public function testNode(): void {
    $this->installEntitySchema('user');
    $this->installEntitySchema('path_alias');
    $this->installEntitySchema('node');
    $this->installEntitySchema('file');
    $this->installEntitySchema('media');
    $this->installSchema('file', 'file_usage');
    $this->installConfig('node');
    $this->installConfig('system');
    $this->createContentType(['type' => 'article']);
    $this->installConfig('xb_dev_standard');
    $this->createMediaType('image', ['id' => 'image', 'label' => 'Image']);
    $this->setUpImages();
    $node = Node::create([
      'type' => 'article',
      'title' => '5 amazing uses for old toothbrushes',
      'status' => FALSE,
      'field_hero' => $this->referencedImage,
      'field_xb_demo' => [],
      'body' => [
        'value' => '',
        'summary' => '',
      ],
    ]);
    self::assertCount(0, $node->validate());
    $this->assertSame(SAVED_NEW, $node->save());

    $envelope = \Drupal::classResolver(ApiLayoutController::class)->get($node);
    \assert($envelope instanceof PreviewEnvelope);
    $matching_client_data = \array_intersect_key($envelope->additionalData, \array_flip(['layout', 'model', 'entity_form_fields']));
    $new_title_client_data = $matching_client_data;
    $new_title_client_data['entity_form_fields']['title[0][value]'] = '5 MORE amazing uses for old toothbrushes';
    $this->assertAutoSaveCreated($node, $matching_client_data, $new_title_client_data);

    // Confirm that adding a component to the node via the client also triggers an auto-save entry.
    $new_component_client_data = $matching_client_data;
    $new_component_client_data['layout'][0]['components'][] = [
      'nodeType' => 'component',
      'uuid' => 'static-image-udf7d',
      'type' => 'sdc.xb_test_sdc.static_image',
      'slots' => [],
    ];
    $this->assertAutoSaveCreated($node, $matching_client_data, $new_component_client_data);
  }

  public function testStagedConfigUpdate(): void {
    $this->installConfig(['system']);

    $sut = $this->container->get(AutoSaveManager::class);
    self::assertInstanceOf(AutoSaveManager::class, $sut);
    StagedConfigUpdate::createFromClientSide([
      'id' => 'xb_change_site_name',
      'label' => 'Change the site name',
      'target' => 'system.site',
      'actions' => [
        [
          'name' => 'simpleConfigUpdate',
          'input' => ['name' => 'My awesome site'],
        ],
      ],
    ])->save();

    $list = $sut->getAllAutoSaveList();
    self::assertCount(1, $list);
    self::assertArrayHasKey('staged_config_update:xb_change_site_name', $list);
    self::assertEquals([
      [
        'name' => 'simpleConfigUpdate',
        'input' => ['name' => 'My awesome site'],
      ],
    ], $list['staged_config_update:xb_change_site_name']['data']['actions']);

    // Prove duplicated saves overwrite the previous one.
    StagedConfigUpdate::createFromClientSide([
      'id' => 'xb_change_site_name',
      'label' => 'Change the site name',
      'target' => 'system.site',
      'actions' => [
        [
          'name' => 'simpleConfigUpdate',
          'input' => ['name' => 'My SUPER AWESOME site'],
        ],
      ],
    ])->save();
    $list = $sut->getAllAutoSaveList();
    self::assertCount(1, $list);
    self::assertArrayHasKey('staged_config_update:xb_change_site_name', $list);
    self::assertEquals([
      [
        'name' => 'simpleConfigUpdate',
        'input' => ['name' => 'My SUPER AWESOME site'],
      ],
    ], $list['staged_config_update:xb_change_site_name']['data']['actions']);

    StagedConfigUpdate::createFromClientSide([
      'id' => 'xb_set_homepage',
      'label' => 'Update the front page',
      'target' => 'system.site',
      'actions' => [
        [
          'name' => 'simpleConfigUpdate',
          'input' => ['page.front' => '/home'],
        ],
      ],
    ])->save();
    $list = $sut->getAllAutoSaveList();
    self::assertCount(2, $list);
    self::assertArrayHasKey('staged_config_update:xb_set_homepage', $list);
    self::assertEquals([
      [
        'name' => 'simpleConfigUpdate',
        'input' => ['name' => 'My SUPER AWESOME site'],
      ],
    ], $list['staged_config_update:xb_change_site_name']['data']['actions']);
    self::assertEquals([
      [
        'name' => 'simpleConfigUpdate',
        'input' => ['page.front' => '/home'],
      ],
    ], $list['staged_config_update:xb_set_homepage']['data']['actions']);
  }

}
