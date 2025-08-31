<?php

declare(strict_types=1);

namespace Drupal\Tests\experience_builder\Kernel;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Cache\MemoryCache\MemoryCacheInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Url;
use Drupal\experience_builder\AutoSave\AutoSaveManager;
use Drupal\experience_builder\Entity\Component;
use Drupal\experience_builder\Entity\ComponentInterface;
use Drupal\experience_builder\Entity\JavaScriptComponent;
use Drupal\experience_builder\Entity\PageRegion;
use Drupal\experience_builder\Plugin\ExperienceBuilder\ComponentSource\JsComponent;
use Drupal\file\FileInterface;
use Drupal\node\Entity\Node;
use Drupal\node\NodeInterface;
use Drupal\Tests\experience_builder\TestSite\XBTestSetup;
use Drupal\Tests\experience_builder\Traits\AutoSaveRequestTestTrait;
use Drupal\Tests\experience_builder\Traits\XBFieldTrait;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * @covers \Drupal\experience_builder\Controller\ApiLayoutController::post()
 * @group experience_builder
 * @group #slow
 */
final class ApiLayoutControllerPostTest extends ApiLayoutControllerTestBase {

  use AutoSaveRequestTestTrait;
  use XBFieldTrait;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->container->get('module_installer')->install(['system', 'block', 'user']);
    $this->container->get('theme_installer')->install(['stark']);
    $this->container->get('config.factory')->getEditable('system.theme')->set('default', 'stark')->save();

    (new XBTestSetup())->setup();
    $this->setUpCurrentUser([], [
      'administer url aliases',
      PageRegion::ADMIN_PERMISSION,
      'edit any article content',
    ]);
  }

  public function testEntityAccessRequired(): void {
    $this->setUpCurrentUser([], [
      'administer url aliases',
    ]);

    $this->expectException(AccessDeniedHttpException::class);
    $this->expectExceptionMessage("The 'edit any article content' permission is required.");
    $node = Node::load(1);
    assert($node instanceof NodeInterface);
    $this->request(Request::create('/xb/api/v0/layout/node/1', method: 'POST', content: json_encode([
      'layout' => [
          [
            'nodeType' => 'region',
            'name' => 'Content',
            'components' => [],
            'id' => 'content',
          ],
      ],
    ] + $this->getPostContentsDefaults($node), JSON_THROW_ON_ERROR)));
  }

  public function testNonEditAccessFieldsFiltered(): void {
    $this->setUpCurrentUser([], [
      'administer url aliases',
      'edit any article content',
    ]);

    // Ensure 'sticky' is currently false and the user does not have edit access to it.
    $node = Node::load(1);
    \assert($node instanceof NodeInterface);
    $this->assertFalse($node->isSticky());
    $this->assertTrue($node->get('sticky')->access('view'));
    $this->assertFalse($node->get('sticky')->access('edit'));
    $this->assertNotEquals('Updated title', $node->label());

    // Make a request that has an updated value for 'sticky'.
    // This request will not throw an AccessException even though the user does
    // not have 'edit' access to the 'sticky' field. While not ideal,
    // importantly the serialized entity values that are stored in the auto-save
    // will not be updated with value sent by the client. This is because we
    // programmatically submit the entity form using
    // `::setProgrammedBypassAccessCheck(FALSE)` to massage the field values
    // before comparing them to the existing saved values. This causes Form API
    // to ignore the updated value for 'sticky' because the user does not have
    // 'edit' access to it.
    $this->request(Request::create('/xb/api/v0/layout/node/1', method: 'POST', content: json_encode([
      'layout' => [
        [
          'nodeType' => 'region',
          'name' => 'Content',
          'components' => [],
          'id' => 'content',
        ],
      ],
      'model' => [],
      'entity_form_fields' => [
        'sticky' => TRUE,
        'title[0][value]' => 'Updated title',
      ],
    ] + $this->getPostContentsDefaults($node), JSON_THROW_ON_ERROR)));
    $autoSave = $this->container->get(AutoSaveManager::class);
    \assert($autoSave instanceof AutoSaveManager);
    $autoSaveEntity = $autoSave->getAutoSaveEntity($node);
    self::assertFalse($autoSaveEntity->isEmpty());
    $entityFromAutoSave = $autoSaveEntity->entity;
    self::assertInstanceOf(NodeInterface::class, $entityFromAutoSave);
    // Ensure that the change to the 'sticky' field was not changed in the
    // auto-save entity.
    self::assertFalse($entityFromAutoSave->isSticky());
    $this->assertSame('Updated title', $entityFromAutoSave->label());
  }

  public function testEmpty(): void {
    $node = Node::load(1);
    assert($node instanceof NodeInterface);
    $response = $this->request(Request::create('/xb/api/v0/layout/node/1', method: 'POST', content: json_encode([
      'layout' => [
        [
          'nodeType' => 'region',
          'name' => 'Content',
          'components' => [],
          'id' => 'content',
        ],
      ],
    ] + $this->getPostContentsDefaults($node), JSON_THROW_ON_ERROR)));
    $this->assertResponseAutoSaves($response, [$node]);

    // Check that the root level is structured correctly.
    $root = $this->getRegion('content');
    $this->assertNotNull($root);
    $this->assertEquals('<div class="xb--region-empty-placeholder"></div>', $root);
  }

  public function testMissingSlot(): void {
    $node = Node::load(1);
    assert($node instanceof NodeInterface);
    $this->request(Request::create('/xb/api/v0/layout/node/1', method: 'POST', content: json_encode([
      'layout' => [
        [
          'nodeType' => 'region',
          'name' => 'Content',
          'components' => [
            [
              'nodeType' => 'component',
              'slots' => [
                [
                  'components' => [],
                  'id' => 'c4074d1f-149a-4662-aaf3-615151531cf6/content',
                  'name' => 'content',
                  'nodeType' => 'slot',
                ],
              ],
              'type' => 'sdc.xb_test_sdc.one_column@836c8835c850cdc5',
              'uuid' => 'c4074d1f-149a-4662-aaf3-615151531cf6',
            ],
          ],
          'id' => 'content',
        ],
      ],
      'model' => [
        'c4074d1f-149a-4662-aaf3-615151531cf6' => [
          'resolved' => [
            'width' => 'full',
          ],
          'source' => [
            'width' => [
              'sourceType' => 'static:field_item:list_string',
              'expression' => 'ℹ︎list_string␟value',
              'sourceTypeSettings' => [
                'storage' => [
                  'allowed_values_function' => 'experience_builder_load_allowed_values_for_component_prop',
                ],
              ],
            ],
          ],
        ],
      ],
    ] + $this->getPostContentsDefaults($node), JSON_THROW_ON_ERROR)));

    // Check that the root level is structured correctly.
    $root = $this->getRegion('content');
    $this->assertNotNull($root);
    $slot_and_component_comments = $this->getComponentInstances($root);
    $this->assertSame(['c4074d1f-149a-4662-aaf3-615151531cf6'], $slot_and_component_comments);
  }

  public function test(): void {
    // Load the test data from the layout controller.
    $response = $this->parentRequest(Request::create('/xb/api/v0/layout/node/1'));
    $node = Node::load(1);
    $this->assertResponseAutoSaves($response, [$node], TRUE);
    $json = self::decodeResponse($response);
    $model = $json['model'];
    $original_content = $response->getContent();
    self::assertIsString($original_content);
    $response = $this->request(Request::create('/xb/api/v0/layout/node/1', method: 'POST', content: $this->filterLayoutForPost($original_content)));
    $this->assertResponseAutoSaves($response, [$node]);
    $autoSave = $this->container->get(AutoSaveManager::class);
    \assert($autoSave instanceof AutoSaveManager);
    \assert($node instanceof NodeInterface);
    self::assertTrue($autoSave->getAutoSaveEntity($node)->isEmpty());

    // Modify the data type of an entity field in the JSON that should not
    // represent a change in the values.
    \assert(\is_string($json['entity_form_fields']['changed']));
    $json['entity_form_fields']['changed'] = (int) $json['entity_form_fields']['changed'];
    $response = $this->request(Request::create('/xb/api/v0/layout/node/1', method: 'POST', content: $this->filterLayoutForPost(\json_encode($json, \JSON_THROW_ON_ERROR))));
    $this->assertResponseAutoSaves($response, [$node]);
    $autoSave = $this->container->get(AutoSaveManager::class);
    \assert($autoSave instanceof AutoSaveManager);
    $node = Node::load(1);
    \assert($node instanceof NodeInterface);
    self::assertTrue($autoSave->getAutoSaveEntity($node)->isEmpty());

    // Check that each level is structured correctly.
    $contentRegion = $this->getRegion('content');
    $this->assertNotNull($contentRegion);
    $slot_and_component_comments = $this->getComponentInstances($contentRegion);
    $this->assertCount(8, $slot_and_component_comments);
    $this->assertSame(array_keys($model), $slot_and_component_comments);

    // Add a new component to the content region.
    $uuid = '173c4899-a5f7-442a-b008-ea8c925735be';
    $json['model'][$uuid] = self::getNewHeadingComponentModel();
    unset($json['isNew'], $json['isPublished'], $json['html']);
    $json['layout'][0]['components'][] = [
      'nodeType' => 'component',
      'uuid' => $uuid,
      'type' => 'sdc.xb_test_sdc.heading@9616e3c4ab9b4fce',
      'slots' => [],
    ];
    // And update the card model to use a URI reference.
    $json['model'][XBTestSetup::UUID_STATIC_CARD1]['resolved']['cta1href'] = 'entity:node/1';
    $json['model'][XBTestSetup::UUID_STATIC_CARD1]['source']['cta1href']['value']['uri'] = 'entity:node/1';

    $json += $this->getPostContentsDefaults($node);
    $response = $this->request(Request::create('/xb/api/v0/layout/node/1', method: 'POST', content: \json_encode($json, JSON_THROW_ON_ERROR)));
    $crawler = new Crawler($this->getRawContent());
    self::assertCount(1, $crawler->filter(\sprintf('a[href="%s"].my-hero__cta--primary', $node->toUrl()->toString())));
    $this->assertResponseAutoSaves($response, [$node]);
    self::assertFalse($autoSave->getAutoSaveEntity($node)->isEmpty());

    $this->assertRequestAutoSaveConflict(Request::create('/xb/api/v0/layout/node/1', method: 'POST', content: $this->filterLayoutForPost($original_content)));

    // Now re-fetch the layout to confirm we don't update the hash if an auto-save
    // entry already exists.
    $content = $this->parentRequest(Request::create('/xb/api/v0/layout/node/1'))->getContent();
    self::assertIsString($content);
    $json = json_decode($content, TRUE);
    $this->assertResponseAutoSaves($response, [$node]);
    self::assertFalse($autoSave->getAutoSaveEntity($node)->isEmpty());
    self::assertArrayHasKey($uuid, $json['model']);
  }

  public function testWithGlobal(): void {
    $regions = PageRegion::createFromBlockLayout('stark');
    foreach ($regions as $region) {
      $region->save();
    }

    // Load the test data from the layout controller.
    $content = $this->parentRequest(Request::create('/xb/api/v0/layout/node/1'))->getContent();
    $this->assertIsString($content);
    $json = json_decode($content, TRUE);
    $highlightedRegion = \array_filter($json['layout'], static fn (array $region) => ($region['id'] ?? NULL) === 'highlighted');
    self::assertCount(1, $highlightedRegion);
    self::assertGreaterThanOrEqual(1, \count(\reset($highlightedRegion)['components']));
    $this->request(Request::create('/xb/api/v0/layout/node/1', method: 'POST', content: $this->filterLayoutForPost($content)));
    $autoSave = $this->container->get(AutoSaveManager::class);
    \assert($autoSave instanceof AutoSaveManager);
    $node = Node::load(1);
    \assert($node instanceof NodeInterface);
    self::assertTrue($autoSave->getAutoSaveEntity($node)->isEmpty());
    foreach ($regions as $region) {
      self::assertTrue($autoSave->getAutoSaveEntity($region)->isEmpty());
    }

    // Check that regions exist and are wrapped.
    $contentRegion = $this->getRegion('content');
    $this->assertNotNull($contentRegion);
    $highlighted = $this->getRegion('highlighted');
    $this->assertNotNull($highlighted);

    // Add a new component to a global region.
    $uuid = '173c4899-a5f7-442a-b008-ea8c925735be';
    $json['model'][$uuid] = self::getNewHeadingComponentModel();
    unset($json['isNew'], $json['isPublished'], $json['html']);
    $json['layout'][\key($highlightedRegion)]['components'][] = [
      'nodeType' => 'component',
      'uuid' => $uuid,
      'type' => 'sdc.xb_test_sdc.heading@9616e3c4ab9b4fce',
      'slots' => [],
    ];
    $json += $this->getPostContentsDefaults($node);
    $this->request(Request::create('/xb/api/v0/layout/node/1', method: 'POST', content: \json_encode($json, JSON_THROW_ON_ERROR)));
    $autoSave = $this->container->get(AutoSaveManager::class);
    \assert($autoSave instanceof AutoSaveManager);
    self::assertTrue($autoSave->getAutoSaveEntity($node)->isEmpty());
    foreach ($regions as $region) {
      \assert($region instanceof PageRegion);
      self::assertEquals($region->get('region') !== 'highlighted', $autoSave->getAutoSaveEntity($region)->isEmpty());
    }
  }

  public function testWithoutPageRegionPermission(): void {
    $this->setUpCurrentUser([], [
      'administer url aliases',
      'edit any article content',
    ]);

    $regions = PageRegion::createFromBlockLayout('stark');
    foreach ($regions as $region) {
      $region->save();
    }

    // Load the test data from the layout controller.
    $content = $this->parentRequest(Request::create('/xb/api/v0/layout/node/1'))->getContent();
    $this->assertIsString($content);
    $json = json_decode($content, TRUE);
    $highlightedRegion = \array_filter($json['layout'], static fn (array $region) => ($region['id'] ?? NULL) === 'highlighted');
    self::assertEmpty($highlightedRegion);
    $this->request(Request::create('/xb/api/v0/layout/node/1', method: 'POST', content: $this->filterLayoutForPost($content)));
    $autoSave = $this->container->get(AutoSaveManager::class);
    \assert($autoSave instanceof AutoSaveManager);
    $node = Node::load(1);
    \assert($node instanceof NodeInterface);
    self::assertTrue($autoSave->getAutoSaveEntity($node)->isEmpty());
    foreach ($regions as $region) {
      self::assertTrue($autoSave->getAutoSaveEntity($region)->isEmpty());
    }

    // Check that content region exist and is wrapped.
    $contentRegion = $this->getRegion('content');
    $this->assertNotNull($contentRegion);
    // But not the highlighted region, as we don't have access to it.
    $highlighted = $this->getRegion('highlighted');
    $this->assertNull($highlighted);

    // Add a new component instance to a ("global") region.
    $uuid = '173c4899-a5f7-442a-b008-ea8c925735be';
    $json['model'][$uuid] = self::getNewHeadingComponentModel();
    unset($json['isNew'], $json['isPublished'], $json['html']);
    $json['layout'][1] = [
      'nodeType' => 'region',
      'id' => 'highlighted',
      'name' => 'Highlighted',
    ];
    $json['layout'][1]['components'][] = [
      'nodeType' => 'component',
      'uuid' => $uuid,
      'type' => 'sdc.xb_test_sdc.heading',
      'slots' => [],
    ];
    $json += $this->getPostContentsDefaults($node);

    $this->expectException(AccessDeniedHttpException::class);
    $this->expectExceptionMessage('Access denied for region highlighted');

    $this->request(Request::create('/xb/api/v0/layout/node/1', method: 'POST', content: \json_encode($json, JSON_THROW_ON_ERROR)));
  }

  public function testWithDraftCodeComponent(): void {
    $this->setUpCurrentUser([], [
      'administer url aliases',
      'edit any article content',
    ]);

    // Create the saved (published) javascript component.
    $saved_component_values = [
      'machineName' => 'hey_there',
      'name' => 'Hey there',
      'status' => TRUE,
      'props' => [
        'name' => [
          'type' => 'string',
          'title' => 'Name',
          'examples' => ['Garry'],
        ],
      ],
      'slots' => [],
      'js' => [
        'original' => 'console.log("Hey there")',
        'compiled' => 'console.log("Hey there")',
      ],
      'css' => [
        'original' => '',
        'compiled' => '',
      ],
    ];
    $code_component = JavaScriptComponent::create($saved_component_values);
    $code_component->save();
    $props = $code_component->get('props');
    $props['voice'] = [
      'type' => 'string',
      'enum' => [
        'polite',
        'shouting',
        'toddler on a sugar high',
      ],
      'title' => 'Voice',
      'examples' => ['polite'],
    ];
    $code_component->set('props', $props);
    $code_component->set('name', 'Here comes the');
    // But store an overridden version in auto-save (draft).
    /** @var \Drupal\experience_builder\AutoSave\AutoSaveManager $autoSave */
    $autoSave = $this->container->get(AutoSaveManager::class);
    $autoSave->saveEntity($code_component);

    // Load the test data from the layout controller.
    $content = $this->parentRequest(Request::create('/xb/api/v0/layout/node/1'))->getContent() ?: '';
    $this->assertJson($content);
    $json = json_decode($content, TRUE, JSON_THROW_ON_ERROR);

    // Add the code component into the layout.
    $uuid = 'ccf36def-3f87-4b7d-bc20-8f8594274818';
    $component = Component::load(JsComponent::componentIdFromJavascriptComponentId((string) $code_component->id()));
    \assert($component instanceof ComponentInterface);
    $json['layout'][0]['components'][] = [
      'nodeType' => 'component',
      'uuid' => $uuid,
      'type' => $component->id() . '@' . $component->getLoadedVersion(),
      'slots' => [],
    ];
    $props = [
      'name' => 'Hot stepper',
      'voice' => 'shouting',
    ];
    $json['model'][$uuid] = [
      'resolved' => $props,
      'source' => [
        'name' => [
          'sourceType' => 'static:field_item:string',
          'expression' => 'ℹ︎string␟value',
        ],
        'voice' => [
          'sourceType' => 'static:field_item:list_string',
          'expression' => 'ℹ︎list_string␟value',
          'sourceTypeSettings' => [
            'storage' => [
              'allowed_values_function' => 'experience_builder_load_allowed_values_for_component_prop',
            ],
          ],
        ],
      ],
    ];

    // Invalidate any static caches.
    $cache = $this->container->get(MemoryCacheInterface::class);
    \assert($cache instanceof MemoryCacheInterface);
    $cache->invalidateTags([\sprintf('entity.memory_cache:%s', JavaScriptComponent::ENTITY_TYPE_ID)]);
    $this->container->get(ConfigFactoryInterface::class)->reset();

    unset($json['isNew'], $json['isPublished'], $json['html']);
    $node = Node::load(1);
    assert($node instanceof NodeInterface);
    $json += $this->getPostContentsDefaults($node);
    $this->request(Request::create('/xb/api/v0/layout/node/1', method: 'POST', content: \json_encode($json, JSON_THROW_ON_ERROR)));
    // Check that regions exist and are wrapped.
    $content_region = $this->getRegion('content');
    self::assertNotNull($content_region);

    $crawler = new Crawler($this->content);
    $element = $crawler->filter('astro-island')->eq(1);
    self::assertNotFalse(str_contains($content_region, 'astro-island'));
    self::assertNotFalse(str_contains($content_region, $uuid));
    self::assertEquals($uuid, $element->attr('uid'));

    // Should see the new (draft) props.
    self::assertJsonStringEqualsJsonString(Json::encode(\array_map(static fn(mixed $value): array => [
      'raw',
      $value,
    ], $props)), $element->attr('props') ?? '');
    // And the new component label.
    self::assertJsonStringEqualsJsonString(Json::encode([
      'name' => 'Here comes the',
      'value' => 'preact',
    ]), $element->attr('opts') ?? '');
    self::assertEquals(Url::fromRoute('experience_builder.api.config.auto-save.get.js', [
      'xb_config_entity_type_id' => JavaScriptComponent::ENTITY_TYPE_ID,
      'xb_config_entity' => 'hey_there',
    ])->toString(), $element->attr('component-url'));
  }

  private static function getNewHeadingComponentModel(): array {
    return [
      'resolved' => [
        'text' => 'This is a random heading.',
        'style' => 'primary',
        'element' => 'h1',
      ],
      'source' => [
        'text' => [
          'sourceType' => 'static:field_item:string',
          'expression' => 'ℹ︎string␟value',
        ],
        'style' => [
          'sourceType' => 'static:field_item:list_string',
          'expression' => 'ℹ︎list_string␟value',
          'sourceTypeSettings' => [
            'storage' => [
              'allowed_values_function' => 'experience_builder_load_allowed_values_for_component_prop',
            ],
          ],
        ],
        'element' => [
          'sourceType' => 'static:field_item:list_string',
          'expression' => 'ℹ︎list_string␟value',
          'sourceTypeSettings' => [
            'storage' => [
              'allowed_values_function' => 'experience_builder_load_allowed_values_for_component_prop',
            ],
          ],
        ],
      ],
    ];
  }

  /**
   * @testWith ["image-optional-with-example", "<img src=\"https://example.com/cat.jpg\" alt=\"Boring placeholder\" />"]
   *           ["image-optional-without-example", ""]
   *           ["image-required-with-example", "<img src=\"!!REFERENCED_MEDIA!!\" alt=\"The bones equal dollars\" />"]
   *           ["image-optional-with-example-and-additional-prop", "<h1><!-- xb-prop-start-166c9eee-35e9-4795-8c6f-24537728e95e/heading -->Heading the right direction?<!-- xb-prop-end-166c9eee-35e9-4795-8c6f-24537728e95e/heading --></h1><img src=\"/XB/MODULE/PATH/tests/modules/xb_test_sdc/components/image-optional-with-example-and-additional-prop/gracie.jpg\" alt=\"A good dog\" width=\"601\" height=\"402\"></img>"]
   *
   * Note: `image-required-without-example` is not tested because it does not meet the requirement.
   * @see \Drupal\Tests\experience_builder\Kernel\Config\ComponentTest::testComponentAutoCreate()
   */
  public function testImageComponentPermutations(string $sdc, string $expected_preview_html): void {
    $content = $this->parentRequest(Request::create('/xb/api/v0/layout/node/1'))->getContent();
    $this->assertIsString($content);
    $json = json_decode($content, TRUE);

    $component = Component::load('sdc.xb_test_sdc.' . $sdc);
    $this->assertInstanceOf(Component::class, $component);

    $client_side = $component->getComponentSource()->getClientSideInfo($component);

    // Add the given SDC to the layout.
    $uuid = '166c9eee-35e9-4795-8c6f-24537728e95e';
    $json['layout'][0]['components'][] = [
      'nodeType' => 'component',
      'uuid' => $uuid,
      'type' => $component->id() . '@' . $component->getLoadedVersion(),
      'slots' => [],
    ];
    $reference_media = \Drupal::entityTypeManager()->getStorage('media')->loadByProperties(
      ['name' => 'The bones are their money'],
    );
    self::assertCount(1, $reference_media);
    $reference_media = \reset($reference_media);
    $node = Node::load(1);
    assert($node instanceof NodeInterface);
    // Populate its client model, and take advantage of the fact that the client
    // model is allowed to be invalid when previewing: no validation may occur,
    // to ensure even invalid explicit inputs for component instances result in
    // a best-effort preview. So, include the superset of all SDC's explicit
    // input, but never provide a value for the image.
    $json['model'][$uuid] = [
      'resolved' => [
        'heading' => 'Heading the right direction?',
        // Resolved will default to the default resolved values.
        // @see addNewComponentToLayout reducer in typescript code.
        'image' => \str_contains($sdc, 'required')
          ? $reference_media->id()
          : ($client_side['propSources']['image']['default_values']['resolved'] ?? NULL),
      ],
      'source' => [
        'heading' => [
          'expression' => 'ℹ︎string␟value',
          'sourceType' => 'static:field_item:string',
        ],
        'image' => [
          'sourceType' => 'static:field_item:entity_reference',
          'expression' => 'ℹ︎entity_reference␟{src↝entity␜␜entity:media:image␝field_media_image␞␟src_with_alternate_widths,alt↝entity␜␜entity:media:image␝field_media_image␞␟alt,width↝entity␜␜entity:media:image␝field_media_image␞␟width,height↝entity␜␜entity:media:image␝field_media_image␞␟height}',
          'sourceTypeSettings' => [
            'storage' => ['target_type' => 'media'],
            'instance' => [
              'handler' => 'default:media',
              'handler_settings' => [
                'target_bundles' => ['image' => 'image'],
              ],
            ],
          ],
          'value' => \str_contains($sdc, 'required') ? $reference_media->id() : NULL,
        ],
      ],
    ];
    $json += $this->getPostContentsDefaults($node);

    // Only the `image-optional-with-example-and-additional-prop` SDC contains a
    // `heading` prop.
    if ($sdc !== 'image-optional-with-example-and-additional-prop') {
      unset($json['model'][$uuid]['resolved']['heading']);
      unset($json['model'][$uuid]['source']['heading']);
    }

    $module_path = \Drupal::service('extension.list.module')->getPath('experience_builder');
    $expected_preview_html = str_replace('XB/MODULE/PATH', $module_path, $expected_preview_html);
    \assert($reference_media->field_media_image->entity instanceof FileInterface);
    // @phpstan-ignore-next-line
    $expected_preview_html = str_replace('!!REFERENCED_MEDIA!!', $reference_media->field_media_image->src_with_alternate_widths, $expected_preview_html);

    unset($json['html'], $json['isPublished'], $json['isNew']);
    $this->request(Request::create('/xb/api/v0/layout/node/1', method: 'POST', content: json_encode($json, JSON_THROW_ON_ERROR)));
    // Ensure the component is rendered using the expected markup.
    $this->assertRaw('<!-- xb-start-166c9eee-35e9-4795-8c6f-24537728e95e -->' . $expected_preview_html . '<!-- xb-end-166c9eee-35e9-4795-8c6f-24537728e95e -->');
  }

  public function testInvalidFormValuesAreReturned(): void {
    $this->setUpCurrentUser([], [
      'administer nodes',
      'administer url aliases',
      PageRegion::ADMIN_PERMISSION,
      'edit any article content',
    ]);
    $content = $this->parentRequest(Request::create('/xb/api/v0/layout/node/1'))->getContent();
    self::assertIsString($content);
    $json = \json_decode($content, TRUE);
    self::assertEquals('Anonymous (0)', $json['entity_form_fields']['uid[0][target_id]']);
    unset($json['html'], $json['isPublished'], $json['isNew']);
    $json['entity_form_fields']['uid[0][target_id]'] = 'This is not a user';
    $node = Node::load(1);
    \assert($node instanceof NodeInterface);
    $json += $this->getPostContentsDefaults($node);
    $content = $this->request(Request::create('/xb/api/v0/layout/node/1', method: 'POST', content: json_encode($json, JSON_THROW_ON_ERROR)));
    self::assertEquals(Response::HTTP_OK, $content->getStatusCode());
    $node = Node::load(1);
    \assert($node instanceof NodeInterface);
    $violations = $this->container->get(AutoSaveManager::class)->getEntityFormViolations($node);
    self::assertCount(1, $violations);
    self::assertEquals('This is not a user', $violations[0]?->getInvalidValue());

    // Even though 'This is not a user' is not a valid user, the GET response
    // should still contain the invalid value the user sent so that another user
    // can fix the invalid value.
    $content = $this->parentRequest(Request::create('/xb/api/v0/layout/node/1'))->getContent();
    self::assertIsString($content);
    $json = \json_decode($content, TRUE);
    self::assertEquals('This is not a user', $json['entity_form_fields']['uid[0][target_id]']);
  }

  public function testUsersWithLesserPermissionsDoNotWipeValuesTheyCannotAccess(): void {
    $admin = $this->setUpCurrentUser([], [
      'administer nodes',
      'administer url aliases',
      PageRegion::ADMIN_PERMISSION,
      'edit any article content',
    ]);
    $node = Node::load(1);
    \assert($node instanceof NodeInterface);
    $original_title = $node->label();
    self::assertEquals(0, (int) $node->getOwnerId());
    $content = $this->parentRequest(Request::create('/xb/api/v0/layout/node/1'))->getContent();
    self::assertIsString($content);
    $json = \json_decode($content, TRUE);
    self::assertEquals('Anonymous (0)', $json['entity_form_fields']['uid[0][target_id]']);
    unset($json['html'], $json['isPublished'], $json['isNew']);
    $json['entity_form_fields']['uid[0][target_id]'] = \sprintf('%s (%d)', $admin->getDisplayName(), $admin->id());
    $response = $this->request(Request::create('/xb/api/v0/layout/node/1', method: 'POST', content: json_encode($json + $this->getPostContentsDefaults($node), JSON_THROW_ON_ERROR)));
    self::assertEquals(Response::HTTP_OK, $response->getStatusCode());

    // We should have an entry in auto-save with the new value.
    self::assertNotNull($node->id());
    $node = $this->container->get(EntityTypeManagerInterface::class)->getStorage('node')->loadUnchanged($node->id());
    \assert($node instanceof NodeInterface);
    self::assertEquals(0, (int) $node->getOwnerId());
    self::assertEquals($original_title, $node->label());
    $autoSave = $this->container->get(AutoSaveManager::class)->getAutoSaveEntity($node);
    self::assertFalse($autoSave->isEmpty());
    \assert($autoSave->entity instanceof NodeInterface);
    self::assertEquals($admin->id(), (int) $autoSave->entity->getOwnerId());
    self::assertEquals($original_title, $autoSave->entity->label());

    // Now login as a user who cannot access that field.
    $this->setUpCurrentUser([], [
      'administer url aliases',
      PageRegion::ADMIN_PERMISSION,
      'edit any article content',
    ]);
    $content = $this->parentRequest(Request::create('/xb/api/v0/layout/node/1'))->getContent();
    self::assertIsString($content);
    $json = \json_decode($content, TRUE);
    // The author field should not be in the response for this user because they
    // do not have the 'administer nodes' permission.
    self::assertArrayNotHasKey('uid[0][target_id]', $json['entity_form_fields']);

    // Make an edit as this user.
    unset($json['html'], $json['isPublished'], $json['isNew']);
    $new_title = $this->randomMachineName();
    $json['entity_form_fields']['title[0][value]'] = $new_title;
    $content = $this->request(Request::create('/xb/api/v0/layout/node/1', method: 'POST', content: json_encode($json + $this->getPostContentsDefaults($node), JSON_THROW_ON_ERROR)));
    self::assertEquals(Response::HTTP_OK, $content->getStatusCode());

    // We should have an entry in auto-save with the new title value, but the
    // edit to the author from the admin user should be retained.
    self::assertNotNull($node->id());
    $node = $this->container->get(EntityTypeManagerInterface::class)->getStorage('node')->loadUnchanged($node->id());
    \assert($node instanceof NodeInterface);
    self::assertEquals(0, (int) $node->getOwnerId());
    self::assertEquals($original_title, $node->label());
    $autoSave = $this->container->get(AutoSaveManager::class)->getAutoSaveEntity($node);
    self::assertFalse($autoSave->isEmpty());
    \assert($autoSave->entity instanceof NodeInterface);
    self::assertEquals($admin->id(), (int) $autoSave->entity->getOwnerId());
    self::assertEquals($new_title, $autoSave->entity->label());
  }

}
