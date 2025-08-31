<?php

declare(strict_types=1);

namespace Drupal\Tests\experience_builder\Kernel;

use ColinODell\PsrTestLogger\TestLogger;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\experience_builder\Entity\ContentTemplate;
use Drupal\experience_builder\EntityHandlers\ContentTemplateAwareViewBuilder;
use Drupal\experience_builder\Plugin\ComponentPluginManager;
use Drupal\filter\Entity\FilterFormat;
use Drupal\KernelTests\KernelTestBase;
use Drupal\node\NodeInterface;
use Drupal\Tests\experience_builder\Traits\GenerateComponentConfigTrait;
use Drupal\Tests\experience_builder\Traits\SingleDirectoryComponentTreeTestTrait;
use Drupal\Tests\experience_builder\Traits\CrawlerTrait;
use Drupal\Tests\node\Traits\ContentTypeCreationTrait;
use Drupal\Tests\node\Traits\NodeCreationTrait;

/**
 * @covers \Drupal\experience_builder\EntityHandlers\ContentTemplateAwareViewBuilder
 * @group experience_builder
 */
final class NodeTemplatesTest extends KernelTestBase {

  use SingleDirectoryComponentTreeTestTrait;
  use GenerateComponentConfigTrait;
  use ContentTypeCreationTrait;
  use NodeCreationTrait;
  use CrawlerTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'experience_builder',
    'system',
    'filter',
    'options',
    'text',
    'field',
    'image',
    'file',
    'user',
    'node',
    'xb_test_rendering',
    'xb_test_sdc',
    'media',
    'link',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installEntitySchema('media');
    $this->installEntitySchema('node');
    $this->installConfig(['node', 'system', 'filter']);
    $this->createContentType(['type' => 'article']);
    // Create config entities for components.
    $this->container->get(ComponentPluginManager::class)->getDefinitions();
    FilterFormat::create([
      'format' => 'basic_html',
      'name' => 'Basic HTML',
      'filters' => [
        'filter_html' => [
          'module' => 'filter',
          'status' => TRUE,
          'weight' => 10,
          'settings' => [
            'allowed_html' => '<p>',
          ],
        ],
      ],
    ])->save();
  }

  public function testOptContentTypeIntoXb(): void {
    ContentTemplate::create([
      'id' => 'node.article.full',
      'content_entity_type_id' => 'node',
      'content_entity_type_bundle' => 'article',
      'content_entity_type_view_mode' => 'full',
      'component_tree' => [
        // A static marker so we can easily tell if we're rendering with XB.
        [
          'uuid' => 'e1f6fbca-e331-4506-9dba-5734194c1e59',
          'component_id' => 'sdc.xb_test_sdc.props-no-slots',
          'inputs' => [
            'heading' => [
              'sourceType' => 'static:field_item:string',
              'value' => 'XB is large and in charge!',
              'expression' => 'ℹ︎string␟value',
            ],
          ],
        ],
        // The node body, which needs to be using a dynamic prop source
        // because all content templates require at least one dynamic prop
        // source.
        [
          'uuid' => '6cf8297a-fc60-4019-be81-c336fd828c39',
          'component_id' => 'sdc.xb_test_sdc.props-no-slots',
          'inputs' => [
            'heading' => [
              'sourceType' => 'dynamic',
              'expression' => 'ℹ︎␜entity:node:article␝body␞␟processed',
            ],
          ],
        ],
      ],
    ])->save();
    $body = <<<HTML
<p>Hey this is allowed</p>
<script>alert('hi mum')</script>
HTML;

    $node = $this->createNode([
      'type' => 'article',
      'body' => [
        'value' => $body,
        'format' => 'basic_html',
      ],
    ]);
    $viewBuilder = $this->container->get(EntityTypeManagerInterface::class)->getViewBuilder('node');
    self::assertInstanceOf(ContentTemplateAwareViewBuilder::class, $viewBuilder);
    $output = $viewBuilder->view($node);
    $crawler = $this->crawlerForRenderArray($output);
    // The content type has not been opted into XB, so it should not be using
    // XB for rendering.
    $html = $crawler->html();
    self::assertStringNotContainsString('XB is large and in charge!', $html);
    self::assertCount(1, $crawler->filter('p:contains("Hey this is allowed")'));
    self::assertCount(0, $crawler->filter('script'));

    // Opt the content type into XB by creating a component tree field.
    $this->createComponentTreeField('node', 'article');

    // Reload the node now that the field definitions have changed.
    self::assertNotNull($node->id());
    $node = $this->container->get(EntityTypeManagerInterface::class)->getStorage('node')->loadUnchanged($node->id());
    \assert($node instanceof NodeInterface);
    // Set up a logger so we can tell if
    // xb_test_rendering_entity_display_build_alter() gets invoked.
    $logger = new TestLogger();
    $this->container->get(LoggerChannelFactoryInterface::class)
      ->get('xb_test')
      ->addLogger($logger);
    $output = $viewBuilder->view($node);
    $crawler = $this->crawlerForRenderArray($output);
    $html = $crawler->html();

    self::assertStringContainsString('XB is large and in charge!', $html);
    self::assertCount(1, $crawler->filter('p:contains("Hey this is allowed")'));
    self::assertCount(0, $crawler->filter('script'));

    // Confirm that hook_entity_display_build_alter() was not invoked.
    // @see xb_test_rendering_entity_display_build_alter()
    $this->assertFalse($logger->hasRecordThatContains("hook_entity_display_build_alter for node {$node->id()} in full view mode"));

    $output = $viewBuilder->view($node, 'teaser');
    $crawler = $this->crawlerForRenderArray($output);
    $html = $crawler->html();
    // Confirm that the template is NOT used when viewing the node as a teaser,
    // even though the content type is opted into XB.
    self::assertStringNotContainsString('XB is large and in charge!', $html);
    self::assertCount(1, $crawler->filter('p:contains("Hey this is allowed")'));
    self::assertCount(0, $crawler->filter('script'));
    $this->assertTrue($logger->hasRecordThatContains("hook_entity_display_build_alter for node {$node->id()} in teaser view mode"));
  }

  /**
   * @covers \Drupal\experience_builder\Entity\ContentTemplate::build
   * @covers \Drupal\experience_builder\Plugin\Validation\Constraint\ComponentTreeStructureConstraintValidator
   */
  public function testExposedSlotsAreFilledByEntity(): void {
    $this->createComponentTreeField('node', 'article');
    $this->generateComponentConfig();

    ContentTemplate::create([
      'content_entity_type_id' => 'node',
      'content_entity_type_bundle' => 'article',
      'content_entity_type_view_mode' => 'full',
      'component_tree' => [
        // A simple SDC that will show the node's title, and has a slot
        // we can expose.
        [
          'uuid' => '2842cc6f-9e2b-42a5-8400-e7d6363e08bf',
          'component_id' => 'sdc.xb_test_sdc.props-slots',
          'inputs' => [
            'heading' => [
              'sourceType' => 'dynamic',
              'expression' => 'ℹ︎␜entity:node:article␝title␞␟value',
            ],
          ],
        ],
      ],
      'exposed_slots' => [
        'custom_content' => [
          'component_uuid' => '2842cc6f-9e2b-42a5-8400-e7d6363e08bf',
          'slot_name' => 'the_body',
          'label' => 'Custom content area',
        ],
      ],
    ])->save();

    // Create an article that fills in the template's exposed slot.
    $node = $this->createNode([
      'type' => 'article',
      'title' => 'The Real Deal',
      'field_component_tree' => [
        [
          'uuid' => '6ea0de84-858a-4f00-9ef5-de02525c8865',
          'component_id' => 'sdc.xb_test_sdc.props-no-slots',
          'inputs' => [
            'heading' => [
              'sourceType' => 'static:field_item:string',
              'value' => "Now we're cooking with gas!",
              'expression' => 'ℹ︎string␟value',
            ],
          ],
          'slot' => 'the_body',
          'parent_uuid' => '2842cc6f-9e2b-42a5-8400-e7d6363e08bf',
        ],
        // If the entity is targeting a slot that doesn't exist in the template,
        // or is not exposed, it shouldn't be an error.
        // @todo This should actually be purged when the entity is saved, so
        //   implement that in https://www.drupal.org/i/3520517.
        [
          'uuid' => '9a1ec750-e016-44fb-9bd2-9a7acb497bd7',
          'component_id' => 'sdc.xb_test_sdc.props-no-slots',
          'slot' => 'ignore_me',
          'parent_uuid' => '2842cc6f-9e2b-42a5-8400-e7d6363e08bf',
          'inputs' => [
            'heading' => [
              'sourceType' => 'static:field_item:string',
              'value' => "This won't show up.",
              'expression' => 'ℹ︎string␟value',
            ],
          ],
        ],
      ],
    ]);
    $viewBuilder = $this->container->get(EntityTypeManagerInterface::class)->getViewBuilder('node');
    self::assertInstanceOf(ContentTemplateAwareViewBuilder::class, $viewBuilder);
    $output = $viewBuilder->view($node);
    $crawler = $this->crawlerForRenderArray($output);
    self::assertCount(1, $crawler->filter('h1:contains("The Real Deal")'));
    self::assertCount(1, $crawler->filter('h1:contains("Now we\'re cooking with gas!")'));
    self::assertStringNotContainsString("This won't show up.", $crawler->text());

    // Although the node targeting a nonexistent slot doesn't break rendering,
    // it DOES mean the entity isn't valid.
    $violations = $node->validate();
    self::assertCount(1, $violations);
    $violation = $violations->get(0);
    self::assertSame('field_component_tree.1.slot', $violation->getPropertyPath());
    self::assertSame('Invalid component subtree. This component subtree contains an invalid slot name for component <em class="placeholder">sdc.xb_test_sdc.props-slots</em>: <em class="placeholder">ignore_me</em>. Valid slot names are: <em class="placeholder">the_body, the_footer, the_colophon</em>.', (string) $violation->getMessage());

    // If we delete the field item, all good!
    $node->get('field_component_tree')->removeItem(1);
    self::assertCount(0, $node->validate());
  }

}
