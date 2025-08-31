<?php

declare(strict_types=1);

namespace Drupal\Tests\experience_builder\Kernel;

use Drupal\Core\Extension\ModuleInstallerInterface;
use Drupal\Core\Plugin\Component;
use Drupal\experience_builder\Entity\Page;
use Drupal\experience_builder\JsonSchemaInterpreter\JsonSchemaStringFormat;
use Drupal\experience_builder\PropExpressions\Component\ComponentPropExpression;
use Drupal\experience_builder\PropExpressions\StructuredData\FieldObjectPropsExpression;
use Drupal\experience_builder\PropExpressions\StructuredData\FieldPropExpression;
use Drupal\experience_builder\PropExpressions\StructuredData\ReferenceFieldPropExpression;
use Drupal\experience_builder\PropShape\PropShape;
use Drupal\experience_builder\JsonSchemaInterpreter\JsonSchemaType;
use Drupal\experience_builder\ShapeMatcher\JsonSchemaFieldInstanceMatcher;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\KernelTests\KernelTestBase;
use Drupal\node\Entity\NodeType;
use Drupal\Tests\experience_builder\Traits\ContribStrictConfigSchemaTestTrait;
use Drupal\Tests\media\Traits\MediaTypeCreationTrait;

/**
 * Tests matching prop shapes against field instances & adapters.
 *
 * To make the test expectations easier to read, this does slightly duplicate
 * some expectations that exist for PropShape::getStorage(). Specifically, the
 * "prop expression" for the computed StaticPropSource is repeated in this test.
 *
 * This provides helpful context about how the constraint-based matching logic
 * is yielding similar or different field type matches.
 *
 * @see docs/data-model.md
 * @see \Drupal\Tests\experience_builder\Kernel\PropShapeRepositoryTest
 * @group experience_builder
 */
class PropShapeToFieldInstanceTest extends KernelTestBase {

  use ContribStrictConfigSchemaTestTrait;
  use MediaTypeCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    // The two only modules Drupal truly requires.
    'system',
    'user',
    // The module being tested.
    'experience_builder',
    // The dependent modules.
    'sdc',
    'file',
    'image',
    'media',
    'filter',
    'ckeditor5',
    'editor',
    'xb_test_sdc',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    // Necessary for uninstalling modules.
    $this->installSchema('user', ['users_data']);
    $this->installEntitySchema(Page::ENTITY_TYPE_ID);
    $this->installEntitySchema('media');
    $this->installEntitySchema('file');
    $this->installConfig('experience_builder');
  }

  /**
   * Tests matches for SDC props.
   *
   * @param string[] $modules
   * @param array{'modules': string[], 'expected': array<string, array<mixed>>} $expected
   *
   * @dataProvider provider
   */
  public function test(array $modules, array $expected): void {
    $missing_test_modules = array_diff($modules, array_keys(\Drupal::service('extension.list.module')->getList()));
    if (!empty($missing_test_modules)) {
      $this->markTestSkipped(sprintf('The %s test modules are missing.', implode(',', $missing_test_modules)));
    }

    $module_installer = \Drupal::service('module_installer');
    assert($module_installer instanceof ModuleInstallerInterface);
    $module_installer->install($modules);

    // Create configurable fields for certain combinations of modules.
    if (empty(array_diff(['node', 'field', 'image'], $modules))) {
      $this->installEntitySchema('node');
      $this->installEntitySchema('field_storage_config');
      $this->installEntitySchema('field_config');
      // Create a "Foo" node type.
      NodeType::create([
        'name' => 'Foo',
        'type' => 'foo',
      ])->save();
      // Create a "silly image" field on the "Foo" node type.
      FieldStorageConfig::create([
        'entity_type' => 'node',
        'field_name' => 'field_silly_image',
        'type' => 'image',
      ])->save();
      FieldConfig::create([
        'entity_type' => 'node',
        'field_name' => 'field_silly_image',
        'bundle' => 'foo',
        'required' => TRUE,
      ])->save();
      // Create a "event duration" field on the "Foo" node type.
      FieldStorageConfig::create([
        'entity_type' => 'node',
        'field_name' => 'field_event_duration',
        'type' => 'daterange',
      ])->save();
      FieldConfig::create([
        'entity_type' => 'node',
        'field_name' => 'field_event_duration',
        'bundle' => 'foo',
        'required' => TRUE,
      ])->save();
      $this->createMediaType('video_file', ['id' => 'baby_videos']);
      $this->createMediaType('video_file', ['id' => 'vacation_videos']);
      FieldStorageConfig::create([
        'field_name' => 'media_video_field',
        'entity_type' => 'node',
        'type' => 'entity_reference',
        'settings' => [
          'target_type' => 'media',
          'required' => TRUE,
        ],
      ])->save();
      FieldConfig::create([
        'label' => 'A Media Video Field',
        'field_name' => 'media_video_field',
        'entity_type' => 'node',
        'bundle' => 'foo',
        'field_type' => 'entity_reference',
        'required' => TRUE,
        'settings' => [
          'handler_settings' => [
            'target_bundles' => [
              'baby_videos' => 'baby_videos',
              'vacation_videos' => 'vacation_videos',
            ],
          ],
        ],
      ])->save();
    }

    $sdc_manager = \Drupal::service('plugin.manager.sdc');
    $matcher = \Drupal::service(JsonSchemaFieldInstanceMatcher::class);
    assert($matcher instanceof JsonSchemaFieldInstanceMatcher);

    $matches = [];
    $components = $sdc_manager->getAllComponents();
    // Ensure the consistent sorting that ComponentPluginManager should have
    // already guaranteed.
    $components = array_combine(
      array_map(fn (Component $c) => $c->getPluginId(), $components),
      $components
    );
    ksort($components);

    // Removing some test components that have been enabled due to all SDCs now
    // in xb_test_sdc module.
    $components_to_remove = ['crash', 'component-no-meta-enum', 'component-mismatch-meta-enum', 'empty-enum', 'deprecated', 'experimental', 'image-gallery', 'image-optional-with-example-and-additional-prop', 'obsolete', 'grid-container', 'html-invalid-format', 'my-cta', 'sparkline', 'sparkline_min_2', 'props-invalid-shapes', 'props-no-examples', 'props-no-slots', 'props-no-title', 'props-slots', 'image-optional-with-example', 'image-optional-without-example', 'image-required-with-example', 'image-required-with-invalid-example', 'image-required-without-example'];
    foreach ($components_to_remove as $key) {
      unset($components['xb_test_sdc:' . $key]);
    }

    foreach ($components as $component) {
      // Do not find a match for every unique SDC prop, but only for unique prop
      // shapes. This avoids a lot of meaningless test expectations.
      foreach (PropShape::getComponentProps($component) as $cpe_string => $prop_shape) {
        $cpe = ComponentPropExpression::fromString($cpe_string);
        // @see https://json-schema.org/understanding-json-schema/reference/object#required
        // @see https://json-schema.org/learn/getting-started-step-by-step#required
        $is_required = in_array($cpe->propName, $component->metadata->schema['required'] ?? [], TRUE);

        $unique_match_key = sprintf('%s, %s',
          $is_required ? 'REQUIRED' : 'optional',
          $prop_shape->uniquePropSchemaKey(),
        );

        $matches[$unique_match_key]['SDC props'][] = $cpe_string;

        if (isset($matches[$unique_match_key]['static prop source'])) {
          continue;
        }

        $schema = $prop_shape->resolvedSchema;

        // 1. compute viable field type + storage settings + instance settings
        // @see \Drupal\experience_builder\PropShape\StorablePropShape::toStaticPropSource()
        // @see \Drupal\experience_builder\PropSource\StaticPropSource()
        $storable_prop_shape = $prop_shape->getStorage();
        $primitive_type = JsonSchemaType::from($schema['type']);
        // 2. find matching field instances
        // @see \Drupal\experience_builder\PropSource\DynamicPropSource
        $instance_candidates = $matcher->findFieldInstanceFormatMatches($primitive_type, $is_required, $schema);
        // 3. adapters.
        // @see \Drupal\experience_builder\PropSource\AdaptedPropSource
        $adapter_output_matches = $matcher->findAdaptersByMatchingOutput($schema);
        $adapter_matches_field_type = [];
        $adapter_matches_instance = [];
        foreach ($adapter_output_matches as $match) {
          foreach ($match->getInputs() as $input_name => $input_schema_ref) {
            $storable_prop_shape_for_adapter_input = PropShape::normalize($input_schema_ref)->getStorage();

            $input_schema = $match->getInputSchema($input_name);
            $input_primitive_type = JsonSchemaType::from(
              is_array($input_schema['type']) ? $input_schema['type'][0] : $input_schema['type']
            );

            $input_is_required = $match->inputIsRequired($input_name);
            $instance_matches = $matcher->findFieldInstanceFormatMatches($input_primitive_type, $input_is_required, $input_schema);

            $adapter_matches_field_type[$match->getPluginId()][$input_name] = $storable_prop_shape_for_adapter_input
              ? (string) $storable_prop_shape_for_adapter_input->fieldTypeProp
              : NULL;
            $adapter_matches_instance[$match->getPluginId()][$input_name] = array_map(fn (FieldPropExpression|ReferenceFieldPropExpression|FieldObjectPropsExpression $e): string => (string) $e, $instance_matches);
          }
          ksort($adapter_matches_field_type);
          ksort($adapter_matches_instance);
        }

        // For each unique required/optional PropShape, store the string
        // representations of the discovered matches to compare against.
        // Note: this is actually already tested in PropShapeRepositoryTest in
        // detail, but this test tries to provide a comprehensive overview.
        // @see \Drupal\Tests\experience_builder\Kernel\PropShapeRepositoryTest
        $matches[$unique_match_key]['static prop source'] = $storable_prop_shape
          ? (string) $storable_prop_shape->fieldTypeProp
          : NULL;
        $matches[$unique_match_key]['instances'] = array_map(fn (FieldPropExpression|ReferenceFieldPropExpression|FieldObjectPropsExpression $e): string => (string) $e, $instance_candidates);
        $matches[$unique_match_key]['adapter_matches_field_type'] = $adapter_matches_field_type;
        $matches[$unique_match_key]['adapter_matches_instance'] = $adapter_matches_instance;
      }
    }

    ksort($matches);
    $this->assertSame($expected, $matches);

    $module_installer->uninstall($modules);
  }

  /**
   * @return array<string, array{'modules': string[], 'expected': array<string, array<mixed>>}>
   */
  public static function provider(): array {
    $cases = [];

    $cases['XB example SDCs + all-props SDC, using ALL core-provided field types + media library without Image-powered media types'] = [
      'modules' => [
        // The module providing the sample SDC to test all JSON schema types.
        'sdc_test_all_props',
        'xb_test_sdc',
        // All other core modules providing field types.
        'comment',
        'datetime',
        'datetime_range',
        'file',
        'image',
        'link',
        'options',
        'path',
        'telephone',
        'text',
        // Create sample configurable fields on the `node` entity type.
        'node',
        'field',
        // The Media Library module being installed does not affect the results
        // of the JsonSchemaFieldInstanceMatcher; it only affects
        // PropShape::getStorage(). Note that zero Image MediaSource-powered
        // Media Types are installed, hence the matching field instances for
        // `$ref: json-schema-definitions://experience_builder.module/image` are
        // image fields, not media reference fields!
        // @see media_library_storage_prop_shape_alter()
        // @see \Drupal\experience_builder\PropShape\PropShape::getStorage()
        // @see \Drupal\experience_builder\ShapeMatcher\JsonSchemaFieldInstanceMatcher
        'media_library',
      ],
      'expected' => [
        'REQUIRED, type=integer&$ref=json-schema-definitions://experience_builder.module/column-width' => [
          'SDC props' => [
            '⿲xb_test_sdc:two_column␟width',
          ],
          'static prop source' => 'ℹ︎list_integer␟value',
          'instances' => [],
          'adapter_matches_field_type' => [],
          'adapter_matches_instance' => [],
        ],
        'REQUIRED, type=object&$ref=json-schema-definitions://experience_builder.module/image' => [
          'SDC props' => [
            '⿲xb_test_sdc:image␟image',
            '⿲xb_test_sdc:image-srcset-candidate-template-uri␟image',
          ],
          'static prop source' => 'ℹ︎image␟{src↠src_with_alternate_widths,alt↠alt,width↠width,height↠height}',
          'instances' => [
            'ℹ︎␜entity:node:foo␝field_silly_image␞␟{src↠src_with_alternate_widths,alt↠alt,width↠width,height↠height}',
          ],
          'adapter_matches_field_type' => [
            'image_apply_style' => [
              'image' => NULL,
              // @todo Figure out best way to describe config entity id via JSON schema.
              'imageStyle' => NULL,
            ],
            'image_url_rel_to_abs' => [
              'image' => 'ℹ︎image␟{src↠src_with_alternate_widths,alt↠alt,width↠width,height↠height}',
            ],
          ],
          'adapter_matches_instance' => [
            'image_apply_style' => [
              'image' => ['ℹ︎␜entity:node:foo␝field_silly_image␞␟{src↝entity␜␜entity:file␝uri␞␟value,width↠width,height↠height,alt↠alt}'],
              'imageStyle' => [],
            ],
            'image_url_rel_to_abs' => [
              'image' => ['ℹ︎␜entity:node:foo␝field_silly_image␞␟{src↠src_with_alternate_widths,alt↠alt,width↠width,height↠height}'],
            ],
          ],
        ],
        'REQUIRED, type=object&$ref=json-schema-definitions://experience_builder.module/video' => [
          'SDC props' => [
            '⿲xb_test_sdc:video␟video',
          ],
          'static prop source' => 'ℹ︎entity_reference␟{src↝entity␜␜entity:media:baby_videos|vacation_videos␝field_media_video_file|field_media_video_file_1␞␟entity␜␜entity:file␝uri␞␟url}',
          'instances' => [
            'ℹ︎␜entity:media:baby_videos␝field_media_video_file␞␟{src↝entity␜␜entity:file␝uri␞␟url}',
            'ℹ︎␜entity:media:vacation_videos␝field_media_video_file_1␞␟{src↝entity␜␜entity:file␝uri␞␟url}',
          ],
          'adapter_matches_field_type' => [],
          'adapter_matches_instance' => [],
        ],
        'REQUIRED, type=string' => [
          'SDC props' => [
            '⿲sdc_test_all_props:all-props␟test_REQUIRED_string',
            '⿲xb_test_sdc:heading␟text',
            '⿲xb_test_sdc:my-hero␟heading',
            '⿲xb_test_sdc:shoe_details␟summary',
            '⿲xb_test_sdc:shoe_tab␟label',
            '⿲xb_test_sdc:shoe_tab␟panel',
            '⿲xb_test_sdc:shoe_tab_panel␟name',
          ],
          'static prop source' => 'ℹ︎string␟value',
          'instances' => [
            'ℹ︎␜entity:media:baby_videos␝name␞␟value',
            'ℹ︎␜entity:media:vacation_videos␝name␞␟value',
            'ℹ︎␜entity:node:foo␝media_video_field␞␟entity␜␜entity:media␝name␞␟value',
            'ℹ︎␜entity:node:foo␝title␞␟value',
            'ℹ︎␜entity:path_alias␝alias␞␟value',
            'ℹ︎␜entity:path_alias␝path␞␟value',
            'ℹ︎␜entity:xb_page␝title␞␟value',
          ],
          'adapter_matches_field_type' => [],
          'adapter_matches_instance' => [],
        ],
        'REQUIRED, type=string&$ref=json-schema-definitions://experience_builder.module/heading-element' => [
          'SDC props' => [
            '⿲xb_test_sdc:heading␟element',
          ],
          'static prop source' => 'ℹ︎list_string␟value',
          'instances' => [],
          'adapter_matches_field_type' => [],
          'adapter_matches_instance' => [],
        ],
        'REQUIRED, type=string&contentMediaType=text/html' => [
          'SDC props' => [
            '⿲sdc_test_all_props:all-props␟test_REQUIRED_string_html',
          ],
          'static prop source' => 'ℹ︎text_long␟value',
          'instances' => [],
          'adapter_matches_field_type' => [],
          'adapter_matches_instance' => [],
        ],
        'REQUIRED, type=string&contentMediaType=text/html&x-formatting-context=block' => [
          'SDC props' => [
            '⿲sdc_test_all_props:all-props␟test_REQUIRED_string_html_block',
          ],
          'static prop source' => 'ℹ︎text_long␟value',
          'instances' => [],
          'adapter_matches_field_type' => [],
          'adapter_matches_instance' => [],
        ],
        'REQUIRED, type=string&contentMediaType=text/html&x-formatting-context=inline' => [
          'SDC props' => [
            '⿲sdc_test_all_props:all-props␟test_REQUIRED_string_html_inline',
          ],
          'static prop source' => 'ℹ︎text␟value',
          'instances' => [],
          'adapter_matches_field_type' => [],
          'adapter_matches_instance' => [],
        ],
        'REQUIRED, type=string&enum[0]=default&enum[1]=primary&enum[2]=success&enum[3]=neutral&enum[4]=warning&enum[5]=danger&enum[6]=text' => [
          'SDC props' => [
            '⿲xb_test_sdc:shoe_button␟variant',
          ],
          'static prop source' => 'ℹ︎list_string␟value',
          'instances' => [],
          'adapter_matches_field_type' => [],
          'adapter_matches_instance' => [],
        ],
        'REQUIRED, type=string&enum[0]=full&enum[1]=wide&enum[2]=normal&enum[3]=narrow' => [
          'SDC props' => [
            '⿲xb_test_sdc:one_column␟width',
          ],
          'static prop source' => 'ℹ︎list_string␟value',
          'instances' => [],
          'adapter_matches_field_type' => [],
          'adapter_matches_instance' => [],
        ],
        'REQUIRED, type=string&enum[0]=moon-stars-fill&enum[1]=moon-stars&enum[2]=star-fill&enum[3]=star&enum[4]=stars&enum[5]=rocket-fill&enum[6]=rocket-takeoff-fill&enum[7]=rocket-takeoff&enum[8]=rocket' => [
          'SDC props' => [
            '⿲xb_test_sdc:shoe_icon␟name',
          ],
          'static prop source' => 'ℹ︎list_string␟value',
          'instances' => [],
          'adapter_matches_field_type' => [],
          'adapter_matches_instance' => [],
        ],
        'REQUIRED, type=string&enum[0]=primary&enum[1]=success&enum[2]=neutral&enum[3]=warning&enum[4]=danger' => [
          'SDC props' => [
            '⿲xb_test_sdc:shoe_badge␟variant',
          ],
          'static prop source' => 'ℹ︎list_string␟value',
          'instances' => [],
          'adapter_matches_field_type' => [],
          'adapter_matches_instance' => [],
        ],
        'REQUIRED, type=string&enum[0]=top&enum[1]=bottom&enum[2]=start&enum[3]=end' => [
          'SDC props' => [
            '⿲xb_test_sdc:shoe_tab_group␟placement',
          ],
          'static prop source' => 'ℹ︎list_string␟value',
          'instances' => [],
          'adapter_matches_field_type' => [],
          'adapter_matches_instance' => [],
        ],
        'REQUIRED, type=string&format=uri' => [
          'SDC props' => [
            '⿲xb_test_sdc:my-hero␟cta1href',
          ],
          'static prop source' => 'ℹ︎link␟url',
          'instances' => [
            'ℹ︎␜entity:file␝uri␞␟url',
            'ℹ︎␜entity:file␝uri␞␟value',
            'ℹ︎␜entity:media:baby_videos␝field_media_video_file␞␟entity␜␜entity:file␝uri␞␟url',
            'ℹ︎␜entity:media:baby_videos␝field_media_video_file␞␟entity␜␜entity:file␝uri␞␟value',
            'ℹ︎␜entity:media:vacation_videos␝field_media_video_file_1␞␟entity␜␜entity:file␝uri␞␟url',
            'ℹ︎␜entity:media:vacation_videos␝field_media_video_file_1␞␟entity␜␜entity:file␝uri␞␟value',
            'ℹ︎␜entity:node:foo␝field_silly_image␞␟entity␜␜entity:file␝uri␞␟url',
            'ℹ︎␜entity:node:foo␝field_silly_image␞␟entity␜␜entity:file␝uri␞␟value',
            'ℹ︎␜entity:node:foo␝field_silly_image␞␟src_with_alternate_widths',
          ],
          'adapter_matches_field_type' => [],
          'adapter_matches_instance' => [],
        ],
        'REQUIRED, type=string&minLength=2' => [
          'SDC props' => [
            '⿲xb_test_sdc:my-section␟text',
          ],
          'static prop source' => 'ℹ︎string␟value',
          'instances' => [
            'ℹ︎␜entity:media:baby_videos␝name␞␟value',
            'ℹ︎␜entity:media:vacation_videos␝name␞␟value',
            'ℹ︎␜entity:node:foo␝media_video_field␞␟entity␜␜entity:media␝name␞␟value',
            'ℹ︎␜entity:node:foo␝title␞␟value',
            'ℹ︎␜entity:path_alias␝alias␞␟value',
            'ℹ︎␜entity:path_alias␝path␞␟value',
            'ℹ︎␜entity:xb_page␝title␞␟value',
          ],
          'adapter_matches_field_type' => [],
          'adapter_matches_instance' => [],
        ],
        'optional, type=array&items[$ref]=json-schema-definitions://experience_builder.module/image&items[type]=object&maxItems=2' => [
          'SDC props' => [
            '⿲sdc_test_all_props:all-props␟test_object_drupal_image_ARRAY',
          ],
          'static prop source' => 'ℹ︎image␟{src↠src_with_alternate_widths,alt↠alt,width↠width,height↠height}',
          'instances' => [],
          'adapter_matches_field_type' => [],
          'adapter_matches_instance' => [],
        ],
        'optional, type=array&items[type]=integer' => [
          'SDC props' => [
            '⿲sdc_test_all_props:all-props␟test_array_integer',
          ],
          'static prop source' => 'ℹ︎integer␟value',
          'instances' => [],
          'adapter_matches_field_type' => [],
          'adapter_matches_instance' => [],
        ],
        'optional, type=array&items[type]=integer&maxItems=2' => [
          'SDC props' => [
            '⿲sdc_test_all_props:all-props␟test_array_integer_maxItems',
          ],
          'static prop source' => 'ℹ︎integer␟value',
          'instances' => [],
          'adapter_matches_field_type' => [],
          'adapter_matches_instance' => [],
        ],
        // ⚠️ This (unsupported!) SDC prop appears here because it's in the `all-props` test-only SDC.
        // @see \Drupal\experience_builder\Plugin\ExperienceBuilder\ComponentSource\SingleDirectoryComponent::componentMeetsRequirements()
        'optional, type=array&items[type]=integer&maxItems=20&minItems=1' => [
          'SDC props' => [
            '⿲sdc_test_all_props:all-props␟test_array_integer_minMaxItems',
          ],
          'static prop source' => NULL,
          'instances' => [],
          'adapter_matches_field_type' => [],
          'adapter_matches_instance' => [],
        ],
        // ⚠️ This (unsupported!) SDC prop appears here because it's in the `all-props` test-only SDC.
        // @see \Drupal\experience_builder\Plugin\ExperienceBuilder\ComponentSource\SingleDirectoryComponent::componentMeetsRequirements()
        'optional, type=array&items[type]=integer&minItems=1' => [
          'SDC props' => [
            '⿲sdc_test_all_props:all-props␟test_array_integer_minItems',
          ],
          'static prop source' => NULL,
          'instances' => [],
          'adapter_matches_field_type' => [],
          'adapter_matches_instance' => [],
        ],
        // ⚠️ This (unsupported!) SDC prop appears here because it's in the `all-props` test-only SDC.
        // @see \Drupal\experience_builder\Plugin\ExperienceBuilder\ComponentSource\SingleDirectoryComponent::componentMeetsRequirements()
        'optional, type=array&items[type]=integer&minItems=2' => [
          'SDC props' => [
            '⿲sdc_test_all_props:all-props␟test_array_integer_minItemsMultiple',
          ],
          'static prop source' => NULL,
          'instances' => [],
          'adapter_matches_field_type' => [],
          'adapter_matches_instance' => [],
        ],
        'optional, type=boolean' => [
          'SDC props' => [
            '⿲sdc_test_all_props:all-props␟test_bool_default_false',
            '⿲sdc_test_all_props:all-props␟test_bool_default_true',
            '⿲xb_test_sdc:shoe_badge␟pill',
            '⿲xb_test_sdc:shoe_badge␟pulse',
            '⿲xb_test_sdc:shoe_button␟disabled',
            '⿲xb_test_sdc:shoe_button␟loading',
            '⿲xb_test_sdc:shoe_button␟outline',
            '⿲xb_test_sdc:shoe_button␟pill',
            '⿲xb_test_sdc:shoe_button␟circle',
            '⿲xb_test_sdc:shoe_details␟open',
            '⿲xb_test_sdc:shoe_details␟disabled',
            '⿲xb_test_sdc:shoe_tab␟active',
            '⿲xb_test_sdc:shoe_tab␟closable',
            '⿲xb_test_sdc:shoe_tab␟disabled',
            '⿲xb_test_sdc:shoe_tab_group␟no_scroll',
            '⿲xb_test_sdc:shoe_tab_panel␟active',
          ],
          'static prop source' => 'ℹ︎boolean␟value',
          'instances' => [
            'ℹ︎␜entity:file␝status␞␟value',
            'ℹ︎␜entity:file␝uid␞␟entity␜␜entity:user␝default_langcode␞␟value',
            'ℹ︎␜entity:file␝uid␞␟entity␜␜entity:user␝status␞␟value',
            'ℹ︎␜entity:media:baby_videos␝default_langcode␞␟value',
            'ℹ︎␜entity:media:baby_videos␝field_media_video_file␞␟display',
            'ℹ︎␜entity:media:baby_videos␝field_media_video_file␞␟entity␜␜entity:file␝status␞␟value',
            'ℹ︎␜entity:media:baby_videos␝revision_default␞␟value',
            'ℹ︎␜entity:media:baby_videos␝revision_user␞␟entity␜␜entity:user␝default_langcode␞␟value',
            'ℹ︎␜entity:media:baby_videos␝revision_user␞␟entity␜␜entity:user␝status␞␟value',
            'ℹ︎␜entity:media:baby_videos␝status␞␟value',
            'ℹ︎␜entity:media:baby_videos␝thumbnail␞␟entity␜␜entity:file␝status␞␟value',
            'ℹ︎␜entity:media:baby_videos␝uid␞␟entity␜␜entity:user␝default_langcode␞␟value',
            'ℹ︎␜entity:media:baby_videos␝uid␞␟entity␜␜entity:user␝status␞␟value',
            'ℹ︎␜entity:media:vacation_videos␝default_langcode␞␟value',
            'ℹ︎␜entity:media:vacation_videos␝field_media_video_file_1␞␟display',
            'ℹ︎␜entity:media:vacation_videos␝field_media_video_file_1␞␟entity␜␜entity:file␝status␞␟value',
            'ℹ︎␜entity:media:vacation_videos␝revision_default␞␟value',
            'ℹ︎␜entity:media:vacation_videos␝revision_user␞␟entity␜␜entity:user␝default_langcode␞␟value',
            'ℹ︎␜entity:media:vacation_videos␝revision_user␞␟entity␜␜entity:user␝status␞␟value',
            'ℹ︎␜entity:media:vacation_videos␝status␞␟value',
            'ℹ︎␜entity:media:vacation_videos␝thumbnail␞␟entity␜␜entity:file␝status␞␟value',
            'ℹ︎␜entity:media:vacation_videos␝uid␞␟entity␜␜entity:user␝default_langcode␞␟value',
            'ℹ︎␜entity:media:vacation_videos␝uid␞␟entity␜␜entity:user␝status␞␟value',
            'ℹ︎␜entity:node:foo␝default_langcode␞␟value',
            'ℹ︎␜entity:node:foo␝field_silly_image␞␟entity␜␜entity:file␝status␞␟value',
            'ℹ︎␜entity:node:foo␝media_video_field␞␟entity␜␜entity:media:baby_videos␝field_media_video_file␞␟display',
            'ℹ︎␜entity:node:foo␝media_video_field␞␟entity␜␜entity:media:vacation_videos␝field_media_video_file_1␞␟display',
            'ℹ︎␜entity:node:foo␝media_video_field␞␟entity␜␜entity:media␝default_langcode␞␟value',
            'ℹ︎␜entity:node:foo␝media_video_field␞␟entity␜␜entity:media␝revision_default␞␟value',
            'ℹ︎␜entity:node:foo␝media_video_field␞␟entity␜␜entity:media␝status␞␟value',
            'ℹ︎␜entity:node:foo␝promote␞␟value',
            'ℹ︎␜entity:node:foo␝revision_default␞␟value',
            'ℹ︎␜entity:node:foo␝revision_uid␞␟entity␜␜entity:user␝default_langcode␞␟value',
            'ℹ︎␜entity:node:foo␝revision_uid␞␟entity␜␜entity:user␝status␞␟value',
            'ℹ︎␜entity:node:foo␝status␞␟value',
            'ℹ︎␜entity:node:foo␝sticky␞␟value',
            'ℹ︎␜entity:node:foo␝uid␞␟entity␜␜entity:user␝default_langcode␞␟value',
            'ℹ︎␜entity:node:foo␝uid␞␟entity␜␜entity:user␝status␞␟value',
            'ℹ︎␜entity:path_alias␝revision_default␞␟value',
            'ℹ︎␜entity:path_alias␝status␞␟value',
            'ℹ︎␜entity:user␝default_langcode␞␟value',
            'ℹ︎␜entity:user␝status␞␟value',
            'ℹ︎␜entity:xb_page␝default_langcode␞␟value',
            'ℹ︎␜entity:xb_page␝image␞␟entity␜␜entity:media␝default_langcode␞␟value',
            'ℹ︎␜entity:xb_page␝image␞␟entity␜␜entity:media␝revision_default␞␟value',
            'ℹ︎␜entity:xb_page␝image␞␟entity␜␜entity:media␝status␞␟value',
            'ℹ︎␜entity:xb_page␝owner␞␟entity␜␜entity:user␝default_langcode␞␟value',
            'ℹ︎␜entity:xb_page␝owner␞␟entity␜␜entity:user␝status␞␟value',
            'ℹ︎␜entity:xb_page␝revision_default␞␟value',
            'ℹ︎␜entity:xb_page␝revision_user␞␟entity␜␜entity:user␝default_langcode␞␟value',
            'ℹ︎␜entity:xb_page␝revision_user␞␟entity␜␜entity:user␝status␞␟value',
            'ℹ︎␜entity:xb_page␝status␞␟value',
          ],
          'adapter_matches_field_type' => [],
          'adapter_matches_instance' => [],
        ],
        'optional, type=integer' => [
          'SDC props' => [
            '⿲sdc_test_all_props:all-props␟test_integer',
          ],
          'static prop source' => 'ℹ︎integer␟value',
          'instances' => [
            'ℹ︎␜entity:file␝changed␞␟value',
            'ℹ︎␜entity:file␝created␞␟value',
            'ℹ︎␜entity:file␝filesize␞␟value',
            'ℹ︎␜entity:file␝uid␞␟entity␜␜entity:user␝access␞␟value',
            'ℹ︎␜entity:file␝uid␞␟entity␜␜entity:user␝changed␞␟value',
            'ℹ︎␜entity:file␝uid␞␟entity␜␜entity:user␝created␞␟value',
            'ℹ︎␜entity:file␝uid␞␟entity␜␜entity:user␝login␞␟value',
            'ℹ︎␜entity:media:baby_videos␝changed␞␟value',
            'ℹ︎␜entity:media:baby_videos␝created␞␟value',
            'ℹ︎␜entity:media:baby_videos␝field_media_video_file␞␟entity␜␜entity:file␝changed␞␟value',
            'ℹ︎␜entity:media:baby_videos␝field_media_video_file␞␟entity␜␜entity:file␝created␞␟value',
            'ℹ︎␜entity:media:baby_videos␝field_media_video_file␞␟entity␜␜entity:file␝filesize␞␟value',
            'ℹ︎␜entity:media:baby_videos␝revision_created␞␟value',
            'ℹ︎␜entity:media:baby_videos␝revision_user␞␟entity␜␜entity:user␝access␞␟value',
            'ℹ︎␜entity:media:baby_videos␝revision_user␞␟entity␜␜entity:user␝changed␞␟value',
            'ℹ︎␜entity:media:baby_videos␝revision_user␞␟entity␜␜entity:user␝created␞␟value',
            'ℹ︎␜entity:media:baby_videos␝revision_user␞␟entity␜␜entity:user␝login␞␟value',
            'ℹ︎␜entity:media:baby_videos␝thumbnail␞␟entity␜␜entity:file␝changed␞␟value',
            'ℹ︎␜entity:media:baby_videos␝thumbnail␞␟entity␜␜entity:file␝created␞␟value',
            'ℹ︎␜entity:media:baby_videos␝thumbnail␞␟entity␜␜entity:file␝filesize␞␟value',
            'ℹ︎␜entity:media:baby_videos␝uid␞␟entity␜␜entity:user␝access␞␟value',
            'ℹ︎␜entity:media:baby_videos␝uid␞␟entity␜␜entity:user␝changed␞␟value',
            'ℹ︎␜entity:media:baby_videos␝uid␞␟entity␜␜entity:user␝created␞␟value',
            'ℹ︎␜entity:media:baby_videos␝uid␞␟entity␜␜entity:user␝login␞␟value',
            'ℹ︎␜entity:media:vacation_videos␝changed␞␟value',
            'ℹ︎␜entity:media:vacation_videos␝created␞␟value',
            'ℹ︎␜entity:media:vacation_videos␝field_media_video_file_1␞␟entity␜␜entity:file␝changed␞␟value',
            'ℹ︎␜entity:media:vacation_videos␝field_media_video_file_1␞␟entity␜␜entity:file␝created␞␟value',
            'ℹ︎␜entity:media:vacation_videos␝field_media_video_file_1␞␟entity␜␜entity:file␝filesize␞␟value',
            'ℹ︎␜entity:media:vacation_videos␝revision_created␞␟value',
            'ℹ︎␜entity:media:vacation_videos␝revision_user␞␟entity␜␜entity:user␝access␞␟value',
            'ℹ︎␜entity:media:vacation_videos␝revision_user␞␟entity␜␜entity:user␝changed␞␟value',
            'ℹ︎␜entity:media:vacation_videos␝revision_user␞␟entity␜␜entity:user␝created␞␟value',
            'ℹ︎␜entity:media:vacation_videos␝revision_user␞␟entity␜␜entity:user␝login␞␟value',
            'ℹ︎␜entity:media:vacation_videos␝thumbnail␞␟entity␜␜entity:file␝changed␞␟value',
            'ℹ︎␜entity:media:vacation_videos␝thumbnail␞␟entity␜␜entity:file␝created␞␟value',
            'ℹ︎␜entity:media:vacation_videos␝thumbnail␞␟entity␜␜entity:file␝filesize␞␟value',
            'ℹ︎␜entity:media:vacation_videos␝uid␞␟entity␜␜entity:user␝access␞␟value',
            'ℹ︎␜entity:media:vacation_videos␝uid␞␟entity␜␜entity:user␝changed␞␟value',
            'ℹ︎␜entity:media:vacation_videos␝uid␞␟entity␜␜entity:user␝created␞␟value',
            'ℹ︎␜entity:media:vacation_videos␝uid␞␟entity␜␜entity:user␝login␞␟value',
            'ℹ︎␜entity:node:foo␝changed␞␟value',
            'ℹ︎␜entity:node:foo␝created␞␟value',
            'ℹ︎␜entity:node:foo␝field_silly_image␞␟entity␜␜entity:file␝changed␞␟value',
            'ℹ︎␜entity:node:foo␝field_silly_image␞␟entity␜␜entity:file␝created␞␟value',
            'ℹ︎␜entity:node:foo␝field_silly_image␞␟entity␜␜entity:file␝filesize␞␟value',
            'ℹ︎␜entity:node:foo␝field_silly_image␞␟height',
            'ℹ︎␜entity:node:foo␝field_silly_image␞␟width',
            'ℹ︎␜entity:node:foo␝media_video_field␞␟entity␜␜entity:media␝changed␞␟value',
            'ℹ︎␜entity:node:foo␝media_video_field␞␟entity␜␜entity:media␝created␞␟value',
            'ℹ︎␜entity:node:foo␝media_video_field␞␟entity␜␜entity:media␝revision_created␞␟value',
            'ℹ︎␜entity:node:foo␝revision_timestamp␞␟value',
            'ℹ︎␜entity:node:foo␝revision_uid␞␟entity␜␜entity:user␝access␞␟value',
            'ℹ︎␜entity:node:foo␝revision_uid␞␟entity␜␜entity:user␝changed␞␟value',
            'ℹ︎␜entity:node:foo␝revision_uid␞␟entity␜␜entity:user␝created␞␟value',
            'ℹ︎␜entity:node:foo␝revision_uid␞␟entity␜␜entity:user␝login␞␟value',
            'ℹ︎␜entity:node:foo␝uid␞␟entity␜␜entity:user␝access␞␟value',
            'ℹ︎␜entity:node:foo␝uid␞␟entity␜␜entity:user␝changed␞␟value',
            'ℹ︎␜entity:node:foo␝uid␞␟entity␜␜entity:user␝created␞␟value',
            'ℹ︎␜entity:node:foo␝uid␞␟entity␜␜entity:user␝login␞␟value',
            'ℹ︎␜entity:user␝access␞␟value',
            'ℹ︎␜entity:user␝changed␞␟value',
            'ℹ︎␜entity:user␝created␞␟value',
            'ℹ︎␜entity:user␝login␞␟value',
            'ℹ︎␜entity:xb_page␝changed␞␟value',
            'ℹ︎␜entity:xb_page␝created␞␟value',
            'ℹ︎␜entity:xb_page␝image␞␟entity␜␜entity:media␝changed␞␟value',
            'ℹ︎␜entity:xb_page␝image␞␟entity␜␜entity:media␝created␞␟value',
            'ℹ︎␜entity:xb_page␝image␞␟entity␜␜entity:media␝revision_created␞␟value',
            'ℹ︎␜entity:xb_page␝owner␞␟entity␜␜entity:user␝access␞␟value',
            'ℹ︎␜entity:xb_page␝owner␞␟entity␜␜entity:user␝changed␞␟value',
            'ℹ︎␜entity:xb_page␝owner␞␟entity␜␜entity:user␝created␞␟value',
            'ℹ︎␜entity:xb_page␝owner␞␟entity␜␜entity:user␝login␞␟value',
            'ℹ︎␜entity:xb_page␝revision_created␞␟value',
            'ℹ︎␜entity:xb_page␝revision_user␞␟entity␜␜entity:user␝access␞␟value',
            'ℹ︎␜entity:xb_page␝revision_user␞␟entity␜␜entity:user␝changed␞␟value',
            'ℹ︎␜entity:xb_page␝revision_user␞␟entity␜␜entity:user␝created␞␟value',
            'ℹ︎␜entity:xb_page␝revision_user␞␟entity␜␜entity:user␝login␞␟value',
          ],
          'adapter_matches_field_type' => [
            'day_count' => [
              'oldest' => 'ℹ︎datetime␟value',
              'newest' => 'ℹ︎datetime␟value',
            ],
          ],
          'adapter_matches_instance' => [
            'day_count' => [
              'oldest' => [
                'ℹ︎␜entity:node:foo␝field_event_duration␞␟end_value',
                'ℹ︎␜entity:node:foo␝field_event_duration␞␟value',
              ],
              'newest' => [
                'ℹ︎␜entity:node:foo␝field_event_duration␞␟end_value',
                'ℹ︎␜entity:node:foo␝field_event_duration␞␟value',
              ],
            ],
          ],
        ],
        'optional, type=integer&enum[0]=1&enum[1]=2' => [
          'SDC props' => [
            0 => '⿲sdc_test_all_props:all-props␟test_integer_enum',
          ],
          'static prop source' => 'ℹ︎list_integer␟value',
          'instances' => [],
          'adapter_matches_field_type' => [],
          'adapter_matches_instance' => [],
        ],
        'optional, type=integer&maximum=2147483648&minimum=-2147483648' => [
          'SDC props' => [
            '⿲sdc_test_all_props:all-props␟test_integer_range_minimum_maximum_timestamps',
          ],
          'static prop source' => 'ℹ︎integer␟value',
          'instances' => [
            'ℹ︎␜entity:file␝uid␞␟entity␜␜entity:user␝access␞␟value',
            'ℹ︎␜entity:file␝uid␞␟entity␜␜entity:user␝login␞␟value',
            'ℹ︎␜entity:media:baby_videos␝revision_user␞␟entity␜␜entity:user␝access␞␟value',
            'ℹ︎␜entity:media:baby_videos␝revision_user␞␟entity␜␜entity:user␝login␞␟value',
            'ℹ︎␜entity:media:baby_videos␝uid␞␟entity␜␜entity:user␝access␞␟value',
            'ℹ︎␜entity:media:baby_videos␝uid␞␟entity␜␜entity:user␝login␞␟value',
            'ℹ︎␜entity:media:vacation_videos␝revision_user␞␟entity␜␜entity:user␝access␞␟value',
            'ℹ︎␜entity:media:vacation_videos␝revision_user␞␟entity␜␜entity:user␝login␞␟value',
            'ℹ︎␜entity:media:vacation_videos␝uid␞␟entity␜␜entity:user␝access␞␟value',
            'ℹ︎␜entity:media:vacation_videos␝uid␞␟entity␜␜entity:user␝login␞␟value',
            'ℹ︎␜entity:node:foo␝revision_uid␞␟entity␜␜entity:user␝access␞␟value',
            'ℹ︎␜entity:node:foo␝revision_uid␞␟entity␜␜entity:user␝login␞␟value',
            'ℹ︎␜entity:node:foo␝uid␞␟entity␜␜entity:user␝access␞␟value',
            'ℹ︎␜entity:node:foo␝uid␞␟entity␜␜entity:user␝login␞␟value',
            'ℹ︎␜entity:user␝access␞␟value',
            'ℹ︎␜entity:user␝login␞␟value',
            'ℹ︎␜entity:xb_page␝owner␞␟entity␜␜entity:user␝access␞␟value',
            'ℹ︎␜entity:xb_page␝owner␞␟entity␜␜entity:user␝login␞␟value',
            'ℹ︎␜entity:xb_page␝revision_user␞␟entity␜␜entity:user␝access␞␟value',
            'ℹ︎␜entity:xb_page␝revision_user␞␟entity␜␜entity:user␝login␞␟value',
          ],
          'adapter_matches_field_type' => [],
          'adapter_matches_instance' => [],
        ],
        'optional, type=integer&minimum=0' => [
          'SDC props' => [
            '⿲sdc_test_all_props:all-props␟test_integer_range_minimum',
          ],
          'static prop source' => 'ℹ︎integer␟value',
          'instances' => [],
          'adapter_matches_field_type' => [],
          'adapter_matches_instance' => [],
        ],
        'optional, type=integer&minimum=1' => [
          'SDC props' => [
            '⿲xb_test_sdc:video␟display_width',
          ],
          'static prop source' => 'ℹ︎integer␟value',
          'instances' => [],
          'adapter_matches_field_type' => [],
          'adapter_matches_instance' => [],
        ],
        'optional, type=number' => [
          'SDC props' => [
            '⿲sdc_test_all_props:all-props␟test_number',
          ],
          'static prop source' => 'ℹ︎float␟value',
          'instances' => [
            'ℹ︎␜entity:file␝changed␞␟value',
            'ℹ︎␜entity:file␝created␞␟value',
            'ℹ︎␜entity:file␝filesize␞␟value',
            'ℹ︎␜entity:file␝uid␞␟entity␜␜entity:user␝access␞␟value',
            'ℹ︎␜entity:file␝uid␞␟entity␜␜entity:user␝changed␞␟value',
            'ℹ︎␜entity:file␝uid␞␟entity␜␜entity:user␝created␞␟value',
            'ℹ︎␜entity:file␝uid␞␟entity␜␜entity:user␝login␞␟value',
            'ℹ︎␜entity:media:baby_videos␝changed␞␟value',
            'ℹ︎␜entity:media:baby_videos␝created␞␟value',
            'ℹ︎␜entity:media:baby_videos␝field_media_video_file␞␟entity␜␜entity:file␝changed␞␟value',
            'ℹ︎␜entity:media:baby_videos␝field_media_video_file␞␟entity␜␜entity:file␝created␞␟value',
            'ℹ︎␜entity:media:baby_videos␝field_media_video_file␞␟entity␜␜entity:file␝filesize␞␟value',
            'ℹ︎␜entity:media:baby_videos␝revision_created␞␟value',
            'ℹ︎␜entity:media:baby_videos␝revision_user␞␟entity␜␜entity:user␝access␞␟value',
            'ℹ︎␜entity:media:baby_videos␝revision_user␞␟entity␜␜entity:user␝changed␞␟value',
            'ℹ︎␜entity:media:baby_videos␝revision_user␞␟entity␜␜entity:user␝created␞␟value',
            'ℹ︎␜entity:media:baby_videos␝revision_user␞␟entity␜␜entity:user␝login␞␟value',
            'ℹ︎␜entity:media:baby_videos␝thumbnail␞␟entity␜␜entity:file␝changed␞␟value',
            'ℹ︎␜entity:media:baby_videos␝thumbnail␞␟entity␜␜entity:file␝created␞␟value',
            'ℹ︎␜entity:media:baby_videos␝thumbnail␞␟entity␜␜entity:file␝filesize␞␟value',
            'ℹ︎␜entity:media:baby_videos␝uid␞␟entity␜␜entity:user␝access␞␟value',
            'ℹ︎␜entity:media:baby_videos␝uid␞␟entity␜␜entity:user␝changed␞␟value',
            'ℹ︎␜entity:media:baby_videos␝uid␞␟entity␜␜entity:user␝created␞␟value',
            'ℹ︎␜entity:media:baby_videos␝uid␞␟entity␜␜entity:user␝login␞␟value',
            'ℹ︎␜entity:media:vacation_videos␝changed␞␟value',
            'ℹ︎␜entity:media:vacation_videos␝created␞␟value',
            'ℹ︎␜entity:media:vacation_videos␝field_media_video_file_1␞␟entity␜␜entity:file␝changed␞␟value',
            'ℹ︎␜entity:media:vacation_videos␝field_media_video_file_1␞␟entity␜␜entity:file␝created␞␟value',
            'ℹ︎␜entity:media:vacation_videos␝field_media_video_file_1␞␟entity␜␜entity:file␝filesize␞␟value',
            'ℹ︎␜entity:media:vacation_videos␝revision_created␞␟value',
            'ℹ︎␜entity:media:vacation_videos␝revision_user␞␟entity␜␜entity:user␝access␞␟value',
            'ℹ︎␜entity:media:vacation_videos␝revision_user␞␟entity␜␜entity:user␝changed␞␟value',
            'ℹ︎␜entity:media:vacation_videos␝revision_user␞␟entity␜␜entity:user␝created␞␟value',
            'ℹ︎␜entity:media:vacation_videos␝revision_user␞␟entity␜␜entity:user␝login␞␟value',
            'ℹ︎␜entity:media:vacation_videos␝thumbnail␞␟entity␜␜entity:file␝changed␞␟value',
            'ℹ︎␜entity:media:vacation_videos␝thumbnail␞␟entity␜␜entity:file␝created␞␟value',
            'ℹ︎␜entity:media:vacation_videos␝thumbnail␞␟entity␜␜entity:file␝filesize␞␟value',
            'ℹ︎␜entity:media:vacation_videos␝uid␞␟entity␜␜entity:user␝access␞␟value',
            'ℹ︎␜entity:media:vacation_videos␝uid␞␟entity␜␜entity:user␝changed␞␟value',
            'ℹ︎␜entity:media:vacation_videos␝uid␞␟entity␜␜entity:user␝created␞␟value',
            'ℹ︎␜entity:media:vacation_videos␝uid␞␟entity␜␜entity:user␝login␞␟value',
            'ℹ︎␜entity:node:foo␝changed␞␟value',
            'ℹ︎␜entity:node:foo␝created␞␟value',
            'ℹ︎␜entity:node:foo␝field_silly_image␞␟entity␜␜entity:file␝changed␞␟value',
            'ℹ︎␜entity:node:foo␝field_silly_image␞␟entity␜␜entity:file␝created␞␟value',
            'ℹ︎␜entity:node:foo␝field_silly_image␞␟entity␜␜entity:file␝filesize␞␟value',
            'ℹ︎␜entity:node:foo␝field_silly_image␞␟height',
            'ℹ︎␜entity:node:foo␝field_silly_image␞␟width',
            'ℹ︎␜entity:node:foo␝media_video_field␞␟entity␜␜entity:media␝changed␞␟value',
            'ℹ︎␜entity:node:foo␝media_video_field␞␟entity␜␜entity:media␝created␞␟value',
            'ℹ︎␜entity:node:foo␝media_video_field␞␟entity␜␜entity:media␝revision_created␞␟value',
            'ℹ︎␜entity:node:foo␝revision_timestamp␞␟value',
            'ℹ︎␜entity:node:foo␝revision_uid␞␟entity␜␜entity:user␝access␞␟value',
            'ℹ︎␜entity:node:foo␝revision_uid␞␟entity␜␜entity:user␝changed␞␟value',
            'ℹ︎␜entity:node:foo␝revision_uid␞␟entity␜␜entity:user␝created␞␟value',
            'ℹ︎␜entity:node:foo␝revision_uid␞␟entity␜␜entity:user␝login␞␟value',
            'ℹ︎␜entity:node:foo␝uid␞␟entity␜␜entity:user␝access␞␟value',
            'ℹ︎␜entity:node:foo␝uid␞␟entity␜␜entity:user␝changed␞␟value',
            'ℹ︎␜entity:node:foo␝uid␞␟entity␜␜entity:user␝created␞␟value',
            'ℹ︎␜entity:node:foo␝uid␞␟entity␜␜entity:user␝login␞␟value',
            'ℹ︎␜entity:user␝access␞␟value',
            'ℹ︎␜entity:user␝changed␞␟value',
            'ℹ︎␜entity:user␝created␞␟value',
            'ℹ︎␜entity:user␝login␞␟value',
            'ℹ︎␜entity:xb_page␝changed␞␟value',
            'ℹ︎␜entity:xb_page␝created␞␟value',
            'ℹ︎␜entity:xb_page␝image␞␟entity␜␜entity:media␝changed␞␟value',
            'ℹ︎␜entity:xb_page␝image␞␟entity␜␜entity:media␝created␞␟value',
            'ℹ︎␜entity:xb_page␝image␞␟entity␜␜entity:media␝revision_created␞␟value',
            'ℹ︎␜entity:xb_page␝owner␞␟entity␜␜entity:user␝access␞␟value',
            'ℹ︎␜entity:xb_page␝owner␞␟entity␜␜entity:user␝changed␞␟value',
            'ℹ︎␜entity:xb_page␝owner␞␟entity␜␜entity:user␝created␞␟value',
            'ℹ︎␜entity:xb_page␝owner␞␟entity␜␜entity:user␝login␞␟value',
            'ℹ︎␜entity:xb_page␝revision_created␞␟value',
            'ℹ︎␜entity:xb_page␝revision_user␞␟entity␜␜entity:user␝access␞␟value',
            'ℹ︎␜entity:xb_page␝revision_user␞␟entity␜␜entity:user␝changed␞␟value',
            'ℹ︎␜entity:xb_page␝revision_user␞␟entity␜␜entity:user␝created␞␟value',
            'ℹ︎␜entity:xb_page␝revision_user␞␟entity␜␜entity:user␝login␞␟value',
          ],
          'adapter_matches_field_type' => [],
          'adapter_matches_instance' => [],
        ],
        'optional, type=object&$ref=json-schema-definitions://experience_builder.module/image' => [
          'SDC props' => [
            '⿲sdc_test_all_props:all-props␟test_object_drupal_image',
          ],
          'static prop source' => 'ℹ︎image␟{src↠src_with_alternate_widths,alt↠alt,width↠width,height↠height}',
          'instances' => [
            'ℹ︎␜entity:node:foo␝field_silly_image␞␟{src↠src_with_alternate_widths,alt↠alt,width↠width,height↠height}',
          ],
          'adapter_matches_field_type' => [
            'image_apply_style' => [
              'image' => NULL,
              'imageStyle' => NULL,
            ],
            'image_url_rel_to_abs' => [
              'image' => 'ℹ︎image␟{src↠src_with_alternate_widths,alt↠alt,width↠width,height↠height}',
            ],
          ],
          'adapter_matches_instance' => [
            'image_apply_style' => [
              'image' => ['ℹ︎␜entity:node:foo␝field_silly_image␞␟{src↝entity␜␜entity:file␝uri␞␟value,width↠width,height↠height,alt↠alt}'],
              'imageStyle' => [],
            ],
            'image_url_rel_to_abs' => [
              'image' => ['ℹ︎␜entity:node:foo␝field_silly_image␞␟{src↠src_with_alternate_widths,alt↠alt,width↠width,height↠height}'],
            ],
          ],
        ],
        'optional, type=object&$ref=json-schema-definitions://experience_builder.module/shoe-icon' => [
          'SDC props' => [
            '⿲xb_test_sdc:shoe_button␟icon',
            '⿲xb_test_sdc:shoe_details␟expand_icon',
            '⿲xb_test_sdc:shoe_details␟collapse_icon',
          ],
          // As shoe-icon has a enum with an empty value, this won't be a valid
          // source.
          'static prop source' => NULL,
          'instances' => [
            'ℹ︎␜entity:media:baby_videos␝field_media_video_file␞␟{label↠description}',
            'ℹ︎␜entity:media:baby_videos␝name␞␟{label↠value}',
            'ℹ︎␜entity:media:baby_videos␝revision_log_message␞␟{label↠value}',
            'ℹ︎␜entity:media:vacation_videos␝field_media_video_file_1␞␟{label↠description}',
            'ℹ︎␜entity:media:vacation_videos␝name␞␟{label↠value}',
            'ℹ︎␜entity:media:vacation_videos␝revision_log_message␞␟{label↠value}',
            'ℹ︎␜entity:node:foo␝field_silly_image␞␟{label↠alt,slot↠title}',
            'ℹ︎␜entity:node:foo␝media_video_field␞␟{label↝entity␜␜entity:media␝revision_log_message␞␟value,slot↝entity␜␜entity:media␝name␞␟value}',
            'ℹ︎␜entity:node:foo␝revision_log␞␟{label↠value}',
            'ℹ︎␜entity:node:foo␝title␞␟{label↠value}',
            'ℹ︎␜entity:path_alias␝alias␞␟{label↠value}',
            'ℹ︎␜entity:path_alias␝path␞␟{label↠value}',
            'ℹ︎␜entity:xb_page␝description␞␟{label↠value}',
            'ℹ︎␜entity:xb_page␝image␞␟{label↝entity␜␜entity:media␝revision_log_message␞␟value,slot↝entity␜␜entity:media␝name␞␟value}',
            'ℹ︎␜entity:xb_page␝revision_log␞␟{label↠value}',
            'ℹ︎␜entity:xb_page␝title␞␟{label↠value}',
          ],
          'adapter_matches_field_type' => [],
          'adapter_matches_instance' => [],
        ],
        'optional, type=object&$ref=json-schema-definitions://experience_builder.module/video' => [
          'SDC props' => [
            '⿲sdc_test_all_props:all-props␟test_object_drupal_video',
          ],
          'static prop source' => 'ℹ︎entity_reference␟{src↝entity␜␜entity:media:baby_videos|vacation_videos␝field_media_video_file|field_media_video_file_1␞␟entity␜␜entity:file␝uri␞␟url}',
          'instances' => [
            'ℹ︎␜entity:media:baby_videos␝field_media_video_file␞␟{src↝entity␜␜entity:file␝uri␞␟url}',
            'ℹ︎␜entity:media:vacation_videos␝field_media_video_file_1␞␟{src↝entity␜␜entity:file␝uri␞␟url}',
          ],
          'adapter_matches_field_type' => [],
          'adapter_matches_instance' => [],
        ],
        'optional, type=object&$ref=json-schema-definitions://sdc_test_all_props.module/date-range' => [
          'SDC props' => [
            '⿲sdc_test_all_props:all-props␟test_object_drupal_date_range',
          ],
          'static prop source' => 'ℹ︎daterange␟{from↠end_value,to↠value}',
          'instances' => [
            'ℹ︎␜entity:node:foo␝field_event_duration␞␟{from↠value,to↠end_value}',
          ],
          'adapter_matches_field_type' => [],
          'adapter_matches_instance' => [],
        ],
        'optional, type=string' => [
          'SDC props' => [
            '⿲sdc_test_all_props:all-props␟test_string',
            '⿲xb_test_sdc:my-hero␟subheading',
            '⿲xb_test_sdc:my-hero␟cta1',
            '⿲xb_test_sdc:my-hero␟cta2',
            '⿲xb_test_sdc:shoe_button␟label',
            '⿲xb_test_sdc:shoe_button␟href',
            '⿲xb_test_sdc:shoe_button␟rel',
            '⿲xb_test_sdc:shoe_button␟download',
            '⿲xb_test_sdc:shoe_icon␟label',
            '⿲xb_test_sdc:shoe_icon␟slot',
          ],
          'static prop source' => 'ℹ︎string␟value',
          'instances' => [
            'ℹ︎␜entity:media:baby_videos␝field_media_video_file␞␟description',
            'ℹ︎␜entity:media:baby_videos␝name␞␟value',
            'ℹ︎␜entity:media:baby_videos␝revision_log_message␞␟value',
            'ℹ︎␜entity:media:vacation_videos␝field_media_video_file_1␞␟description',
            'ℹ︎␜entity:media:vacation_videos␝name␞␟value',
            'ℹ︎␜entity:media:vacation_videos␝revision_log_message␞␟value',
            'ℹ︎␜entity:node:foo␝field_silly_image␞␟alt',
            'ℹ︎␜entity:node:foo␝field_silly_image␞␟title',
            'ℹ︎␜entity:node:foo␝media_video_field␞␟entity␜␜entity:media:baby_videos␝field_media_video_file␞␟description',
            'ℹ︎␜entity:node:foo␝media_video_field␞␟entity␜␜entity:media:vacation_videos␝field_media_video_file_1␞␟description',
            'ℹ︎␜entity:node:foo␝media_video_field␞␟entity␜␜entity:media␝name␞␟value',
            'ℹ︎␜entity:node:foo␝media_video_field␞␟entity␜␜entity:media␝revision_log_message␞␟value',
            'ℹ︎␜entity:node:foo␝revision_log␞␟value',
            'ℹ︎␜entity:node:foo␝title␞␟value',
            'ℹ︎␜entity:path_alias␝alias␞␟value',
            'ℹ︎␜entity:path_alias␝path␞␟value',
            'ℹ︎␜entity:xb_page␝description␞␟value',
            'ℹ︎␜entity:xb_page␝image␞␟entity␜␜entity:media␝name␞␟value',
            'ℹ︎␜entity:xb_page␝image␞␟entity␜␜entity:media␝revision_log_message␞␟value',
            'ℹ︎␜entity:xb_page␝revision_log␞␟value',
            'ℹ︎␜entity:xb_page␝title␞␟value',
          ],
          'adapter_matches_field_type' => [],
          'adapter_matches_instance' => [],
        ],
        'optional, type=string&$ref=json-schema-definitions://experience_builder.module/image-uri' => [
          'SDC props' => [
            '⿲sdc_test_all_props:all-props␟test_string_format_' . JsonSchemaStringFormat::URI->value . '_image',
          ],
          'static prop source' => NULL,
          'instances' => [
            'ℹ︎␜entity:media:baby_videos␝thumbnail␞␟entity␜␜entity:file␝uri␞␟url',
            'ℹ︎␜entity:media:baby_videos␝thumbnail␞␟src_with_alternate_widths',
            'ℹ︎␜entity:media:vacation_videos␝thumbnail␞␟entity␜␜entity:file␝uri␞␟url',
            'ℹ︎␜entity:media:vacation_videos␝thumbnail␞␟src_with_alternate_widths',
            'ℹ︎␜entity:node:foo␝field_silly_image␞␟entity␜␜entity:file␝uri␞␟url',
            'ℹ︎␜entity:node:foo␝field_silly_image␞␟src_with_alternate_widths',
            'ℹ︎␜entity:node:foo␝media_video_field␞␟entity␜␜entity:media␝thumbnail␞␟src_with_alternate_widths',
            'ℹ︎␜entity:xb_page␝image␞␟entity␜␜entity:media␝thumbnail␞␟src_with_alternate_widths',
          ],
          'adapter_matches_field_type' => [
            'image_extract_url' => [
              'imageUri' => NULL,
            ],
          ],
          'adapter_matches_instance' => [
            'image_extract_url' => [
              'imageUri' => [
                'ℹ︎␜entity:media:baby_videos␝thumbnail␞␟entity␜␜entity:file␝uri␞␟value',
                'ℹ︎␜entity:media:vacation_videos␝thumbnail␞␟entity␜␜entity:file␝uri␞␟value',
                'ℹ︎␜entity:node:foo␝field_silly_image␞␟entity␜␜entity:file␝uri␞␟value',
              ],
            ],
          ],
        ],
        'optional, type=string&$ref=json-schema-definitions://experience_builder.module/textarea' => [
          'SDC props' => [
            '⿲sdc_test_all_props:all-props␟test_string_multiline',
          ],
          'static prop source' => 'ℹ︎string_long␟value',
          'instances' => [
            'ℹ︎␜entity:media:baby_videos␝revision_log_message␞␟value',
            'ℹ︎␜entity:media:vacation_videos␝revision_log_message␞␟value',
            'ℹ︎␜entity:node:foo␝media_video_field␞␟entity␜␜entity:media␝revision_log_message␞␟value',
            'ℹ︎␜entity:node:foo␝revision_log␞␟value',
            'ℹ︎␜entity:xb_page␝description␞␟value',
            'ℹ︎␜entity:xb_page␝image␞␟entity␜␜entity:media␝revision_log_message␞␟value',
            'ℹ︎␜entity:xb_page␝revision_log␞␟value',
          ],
          'adapter_matches_field_type' => [],
          'adapter_matches_instance' => [],
        ],
        'optional, type=string&contentMediaType=text/html' => [
          'SDC props' => [
            '⿲sdc_test_all_props:all-props␟test_string_html',
          ],
          'static prop source' => 'ℹ︎text_long␟value',
          'instances' => [],
          'adapter_matches_field_type' => [],
          'adapter_matches_instance' => [],
        ],
        'optional, type=string&contentMediaType=text/html&x-formatting-context=block' => [
          'SDC props' => [
            '⿲sdc_test_all_props:all-props␟test_string_html_block',
          ],
          'static prop source' => 'ℹ︎text_long␟value',
          'instances' => [],
          'adapter_matches_field_type' => [],
          'adapter_matches_instance' => [],
        ],
        'optional, type=string&contentMediaType=text/html&x-formatting-context=inline' => [
          'SDC props' => [
            '⿲sdc_test_all_props:all-props␟test_string_html_inline',
          ],
          'static prop source' => 'ℹ︎text␟value',
          'instances' => [],
          'adapter_matches_field_type' => [],
          'adapter_matches_instance' => [],
        ],
        'optional, type=string&enum[0]=&enum[1]=base&enum[2]=l&enum[3]=s&enum[4]=xs&enum[5]=xxs' => [
          'SDC props' => [
            '⿲xb_test_sdc:shoe_icon␟size',
          ],
          // As shoe-icon has a enum with an empty value, this won't be a valid
          // source.
          'static prop source' => NULL,
          'instances' => [],
          'adapter_matches_field_type' => [],
          'adapter_matches_instance' => [],
        ],
        'optional, type=string&enum[0]=&enum[1]=gray&enum[2]=primary&enum[3]=neutral-soft&enum[4]=neutral-medium&enum[5]=neutral-loud&enum[6]=primary-medium&enum[7]=primary-loud&enum[8]=black&enum[9]=white&enum[10]=red&enum[11]=gold&enum[12]=green' => [
          'SDC props' => [
            '⿲xb_test_sdc:shoe_icon␟color',
          ],
          // As shoe-icon has a enum with an empty value, this won't be a valid
          // source.
          'static prop source' => NULL,
          'instances' => [],
          'adapter_matches_field_type' => [],
          'adapter_matches_instance' => [],
        ],
        'optional, type=string&enum[0]=_blank&enum[1]=_parent&enum[2]=_self&enum[3]=_top' => [
          'SDC props' => [
            '⿲xb_test_sdc:shoe_button␟target',
          ],
          'static prop source' => 'ℹ︎list_string␟value',
          'instances' => [],
          'adapter_matches_field_type' => [],
          'adapter_matches_instance' => [],
        ],
        'optional, type=string&enum[0]=auto&enum[1]=manual' => [
          'SDC props' => [
            '⿲xb_test_sdc:shoe_tab_group␟activation',
          ],
          'static prop source' => 'ℹ︎list_string␟value',
          'instances' => [],
          'adapter_matches_field_type' => [],
          'adapter_matches_instance' => [],
        ],
        'optional, type=string&enum[0]=foo&enum[1]=bar' => [
          'SDC props' => [
            '⿲sdc_test_all_props:all-props␟test_string_enum',
          ],
          'static prop source' => 'ℹ︎list_string␟value',
          'instances' => [],
          'adapter_matches_field_type' => [],
          'adapter_matches_instance' => [],
        ],
        'optional, type=string&enum[0]=prefix&enum[1]=suffix' => [
          'SDC props' => [
            '⿲xb_test_sdc:shoe_button␟icon_position',
          ],
          'static prop source' => 'ℹ︎list_string␟value',
          'instances' => [],
          'adapter_matches_field_type' => [],
          'adapter_matches_instance' => [],
        ],
        'optional, type=string&enum[0]=primary&enum[1]=secondary' => [
          'SDC props' => [
            '⿲xb_test_sdc:heading␟style',
          ],
          'static prop source' => 'ℹ︎list_string␟value',
          'instances' => [],
          'adapter_matches_field_type' => [],
          'adapter_matches_instance' => [],
        ],
        'optional, type=string&enum[0]=small&enum[1]=medium&enum[2]=large' => [
          'SDC props' => [
            '⿲xb_test_sdc:shoe_button␟size',
          ],
          'static prop source' => 'ℹ︎list_string␟value',
          'instances' => [],
          'adapter_matches_field_type' => [],
          'adapter_matches_instance' => [],
        ],
        'optional, type=string&format=date' => [
          'SDC props' => [
            '⿲sdc_test_all_props:all-props␟test_string_format_' . JsonSchemaStringFormat::DATE->value,
          ],
          'static prop source' => 'ℹ︎datetime␟value',
          'instances' => [
            'ℹ︎␜entity:node:foo␝field_event_duration␞␟end_value',
            'ℹ︎␜entity:node:foo␝field_event_duration␞␟value',
          ],
          'adapter_matches_field_type' => [
            'unix_to_date' => [
              'unix' => 'ℹ︎integer␟value',
            ],
          ],
          'adapter_matches_instance' => [
            'unix_to_date' => [
              'unix' => [],
            ],
          ],
        ],
        'optional, type=string&format=date-time' => [
          'SDC props' => [
            '⿲sdc_test_all_props:all-props␟test_string_format_' . str_replace('-', '_', JsonSchemaStringFormat::DATE_TIME->value),
          ],
          'static prop source' => 'ℹ︎datetime␟value',
          'instances' => [
            'ℹ︎␜entity:node:foo␝field_event_duration␞␟end_value',
            'ℹ︎␜entity:node:foo␝field_event_duration␞␟value',
          ],
          'adapter_matches_field_type' => [],
          'adapter_matches_instance' => [],
        ],
        'optional, type=string&format=duration' => [
          'SDC props' => [
            '⿲sdc_test_all_props:all-props␟test_string_format_' . JsonSchemaStringFormat::DURATION->value,
          ],
          // @todo No field type in Drupal core uses \Drupal\Core\TypedData\Plugin\DataType\DurationIso8601.
          'static prop source' => NULL,
          'instances' => [],
          'adapter_matches_field_type' => [],
          'adapter_matches_instance' => [],
        ],
        'optional, type=string&format=email' => [
          'SDC props' => [
            '⿲sdc_test_all_props:all-props␟test_string_format_' . JsonSchemaStringFormat::EMAIL->value,
          ],
          'static prop source' => 'ℹ︎email␟value',
          'instances' => [
            'ℹ︎␜entity:file␝uid␞␟entity␜␜entity:user␝init␞␟value',
            'ℹ︎␜entity:file␝uid␞␟entity␜␜entity:user␝mail␞␟value',
            'ℹ︎␜entity:media:baby_videos␝revision_user␞␟entity␜␜entity:user␝init␞␟value',
            'ℹ︎␜entity:media:baby_videos␝revision_user␞␟entity␜␜entity:user␝mail␞␟value',
            'ℹ︎␜entity:media:baby_videos␝uid␞␟entity␜␜entity:user␝init␞␟value',
            'ℹ︎␜entity:media:baby_videos␝uid␞␟entity␜␜entity:user␝mail␞␟value',
            'ℹ︎␜entity:media:vacation_videos␝revision_user␞␟entity␜␜entity:user␝init␞␟value',
            'ℹ︎␜entity:media:vacation_videos␝revision_user␞␟entity␜␜entity:user␝mail␞␟value',
            'ℹ︎␜entity:media:vacation_videos␝uid␞␟entity␜␜entity:user␝init␞␟value',
            'ℹ︎␜entity:media:vacation_videos␝uid␞␟entity␜␜entity:user␝mail␞␟value',
            'ℹ︎␜entity:node:foo␝revision_uid␞␟entity␜␜entity:user␝init␞␟value',
            'ℹ︎␜entity:node:foo␝revision_uid␞␟entity␜␜entity:user␝mail␞␟value',
            'ℹ︎␜entity:node:foo␝uid␞␟entity␜␜entity:user␝init␞␟value',
            'ℹ︎␜entity:node:foo␝uid␞␟entity␜␜entity:user␝mail␞␟value',
            'ℹ︎␜entity:user␝init␞␟value',
            'ℹ︎␜entity:user␝mail␞␟value',
            'ℹ︎␜entity:xb_page␝owner␞␟entity␜␜entity:user␝init␞␟value',
            'ℹ︎␜entity:xb_page␝owner␞␟entity␜␜entity:user␝mail␞␟value',
            'ℹ︎␜entity:xb_page␝revision_user␞␟entity␜␜entity:user␝init␞␟value',
            'ℹ︎␜entity:xb_page␝revision_user␞␟entity␜␜entity:user␝mail␞␟value',
          ],
          'adapter_matches_field_type' => [],
          'adapter_matches_instance' => [],
        ],
        'optional, type=string&format=hostname' => [
          'SDC props' => [
            '⿲sdc_test_all_props:all-props␟test_string_format_' . JsonSchemaStringFormat::HOSTNAME->value,
          ],
          // @todo adapter from `type: string, format=uri`?
          'static prop source' => NULL,
          'instances' => [],
          'adapter_matches_field_type' => [],
          'adapter_matches_instance' => [],
        ],
        'optional, type=string&format=idn-email' => [
          'SDC props' => [
            '⿲sdc_test_all_props:all-props␟test_string_format_' . str_replace('-', '_', JsonSchemaStringFormat::IDN_EMAIL->value),
          ],
          'static prop source' => 'ℹ︎email␟value',
          'instances' => [
            'ℹ︎␜entity:file␝uid␞␟entity␜␜entity:user␝init␞␟value',
            'ℹ︎␜entity:file␝uid␞␟entity␜␜entity:user␝mail␞␟value',
            'ℹ︎␜entity:media:baby_videos␝revision_user␞␟entity␜␜entity:user␝init␞␟value',
            'ℹ︎␜entity:media:baby_videos␝revision_user␞␟entity␜␜entity:user␝mail␞␟value',
            'ℹ︎␜entity:media:baby_videos␝uid␞␟entity␜␜entity:user␝init␞␟value',
            'ℹ︎␜entity:media:baby_videos␝uid␞␟entity␜␜entity:user␝mail␞␟value',
            'ℹ︎␜entity:media:vacation_videos␝revision_user␞␟entity␜␜entity:user␝init␞␟value',
            'ℹ︎␜entity:media:vacation_videos␝revision_user␞␟entity␜␜entity:user␝mail␞␟value',
            'ℹ︎␜entity:media:vacation_videos␝uid␞␟entity␜␜entity:user␝init␞␟value',
            'ℹ︎␜entity:media:vacation_videos␝uid␞␟entity␜␜entity:user␝mail␞␟value',
            'ℹ︎␜entity:node:foo␝revision_uid␞␟entity␜␜entity:user␝init␞␟value',
            'ℹ︎␜entity:node:foo␝revision_uid␞␟entity␜␜entity:user␝mail␞␟value',
            'ℹ︎␜entity:node:foo␝uid␞␟entity␜␜entity:user␝init␞␟value',
            'ℹ︎␜entity:node:foo␝uid␞␟entity␜␜entity:user␝mail␞␟value',
            'ℹ︎␜entity:user␝init␞␟value',
            'ℹ︎␜entity:user␝mail␞␟value',
            'ℹ︎␜entity:xb_page␝owner␞␟entity␜␜entity:user␝init␞␟value',
            'ℹ︎␜entity:xb_page␝owner␞␟entity␜␜entity:user␝mail␞␟value',
            'ℹ︎␜entity:xb_page␝revision_user␞␟entity␜␜entity:user␝init␞␟value',
            'ℹ︎␜entity:xb_page␝revision_user␞␟entity␜␜entity:user␝mail␞␟value',
          ],
          'adapter_matches_field_type' => [],
          'adapter_matches_instance' => [],
        ],
        'optional, type=string&format=idn-hostname' => [
          'SDC props' => [
            '⿲sdc_test_all_props:all-props␟test_string_format_' . str_replace('-', '_', JsonSchemaStringFormat::IDN_HOSTNAME->value),
          ],
          // phpcs:disable
          // @todo adapter from `type: string, format=uri`?
          // @todo To generate a match for this JSON schema type:
          // - generate an adapter?! -> but we cannot just adapt arbitrary data to generate a IP
          // - follow entity references in the actual data model, i.e. this will find matches at the instance level? -> but does not allow the BUILDER persona to create instances
          // - create an instance with the necessary requirement?! => `@FieldType=string` + `Ip` constraint … but no field type allows configuring this?
          // phpcs:enable
          'static prop source' => NULL,
          'instances' => [],
          'adapter_matches_field_type' => [],
          'adapter_matches_instance' => [],
        ],
        // @todo Update \Drupal\sdc\Component\ComponentValidator to disallow this — does not make sense for presenting information?
        'optional, type=string&format=ipv4' => [
          'SDC props' => [
            '⿲sdc_test_all_props:all-props␟test_string_format_' . JsonSchemaStringFormat::IPV4->value,
          ],
          'static prop source' => NULL,
          'instances' => [],
          'adapter_matches_field_type' => [],
          'adapter_matches_instance' => [],
        ],
        // @todo Update \Drupal\sdc\Component\ComponentValidator to disallow this — does not make sense for presenting information?
        'optional, type=string&format=ipv6' => [
          'SDC props' => [
            '⿲sdc_test_all_props:all-props␟test_string_format_' . JsonSchemaStringFormat::IPV6->value,
          ],
          'static prop source' => NULL,
          'instances' => [],
          'adapter_matches_field_type' => [],
          'adapter_matches_instance' => [],
        ],
        'optional, type=string&format=iri' => [
          'SDC props' => [
            '⿲sdc_test_all_props:all-props␟test_string_format_' . JsonSchemaStringFormat::IRI->value,
          ],
          'static prop source' => 'ℹ︎link␟url',
          'instances' => [
            'ℹ︎␜entity:file␝uri␞␟url',
            'ℹ︎␜entity:file␝uri␞␟value',
            'ℹ︎␜entity:media:baby_videos␝field_media_video_file␞␟entity␜␜entity:file␝uri␞␟url',
            'ℹ︎␜entity:media:baby_videos␝field_media_video_file␞␟entity␜␜entity:file␝uri␞␟value',
            'ℹ︎␜entity:media:baby_videos␝thumbnail␞␟entity␜␜entity:file␝uri␞␟url',
            'ℹ︎␜entity:media:baby_videos␝thumbnail␞␟entity␜␜entity:file␝uri␞␟value',
            'ℹ︎␜entity:media:baby_videos␝thumbnail␞␟src_with_alternate_widths',
            'ℹ︎␜entity:media:vacation_videos␝field_media_video_file_1␞␟entity␜␜entity:file␝uri␞␟url',
            'ℹ︎␜entity:media:vacation_videos␝field_media_video_file_1␞␟entity␜␜entity:file␝uri␞␟value',
            'ℹ︎␜entity:media:vacation_videos␝thumbnail␞␟entity␜␜entity:file␝uri␞␟url',
            'ℹ︎␜entity:media:vacation_videos␝thumbnail␞␟entity␜␜entity:file␝uri␞␟value',
            'ℹ︎␜entity:media:vacation_videos␝thumbnail␞␟src_with_alternate_widths',
            'ℹ︎␜entity:node:foo␝field_silly_image␞␟entity␜␜entity:file␝uri␞␟url',
            'ℹ︎␜entity:node:foo␝field_silly_image␞␟entity␜␜entity:file␝uri␞␟value',
            'ℹ︎␜entity:node:foo␝field_silly_image␞␟src_with_alternate_widths',
            'ℹ︎␜entity:node:foo␝media_video_field␞␟entity␜␜entity:media␝thumbnail␞␟src_with_alternate_widths',
            'ℹ︎␜entity:xb_page␝image␞␟entity␜␜entity:media␝thumbnail␞␟src_with_alternate_widths',
          ],
          'adapter_matches_field_type' => [],
          'adapter_matches_instance' => [],
        ],
        'optional, type=string&format=iri-reference' => [
          'SDC props' => [
            '⿲sdc_test_all_props:all-props␟test_string_format_' . str_replace('-', '_', JsonSchemaStringFormat::IRI_REFERENCE->value),
          ],
          'static prop source' => 'ℹ︎link␟url',
          'instances' => [
            'ℹ︎␜entity:file␝uri␞␟url',
            'ℹ︎␜entity:file␝uri␞␟value',
            'ℹ︎␜entity:media:baby_videos␝field_media_video_file␞␟entity␜␜entity:file␝uri␞␟url',
            'ℹ︎␜entity:media:baby_videos␝field_media_video_file␞␟entity␜␜entity:file␝uri␞␟value',
            'ℹ︎␜entity:media:baby_videos␝thumbnail␞␟entity␜␜entity:file␝uri␞␟url',
            'ℹ︎␜entity:media:baby_videos␝thumbnail␞␟entity␜␜entity:file␝uri␞␟value',
            'ℹ︎␜entity:media:baby_videos␝thumbnail␞␟src_with_alternate_widths',
            'ℹ︎␜entity:media:vacation_videos␝field_media_video_file_1␞␟entity␜␜entity:file␝uri␞␟url',
            'ℹ︎␜entity:media:vacation_videos␝field_media_video_file_1␞␟entity␜␜entity:file␝uri␞␟value',
            'ℹ︎␜entity:media:vacation_videos␝thumbnail␞␟entity␜␜entity:file␝uri␞␟url',
            'ℹ︎␜entity:media:vacation_videos␝thumbnail␞␟entity␜␜entity:file␝uri␞␟value',
            'ℹ︎␜entity:media:vacation_videos␝thumbnail␞␟src_with_alternate_widths',
            'ℹ︎␜entity:node:foo␝field_silly_image␞␟entity␜␜entity:file␝uri␞␟url',
            'ℹ︎␜entity:node:foo␝field_silly_image␞␟entity␜␜entity:file␝uri␞␟value',
            'ℹ︎␜entity:node:foo␝field_silly_image␞␟src_with_alternate_widths',
            'ℹ︎␜entity:node:foo␝media_video_field␞␟entity␜␜entity:media␝thumbnail␞␟src_with_alternate_widths',
            'ℹ︎␜entity:xb_page␝image␞␟entity␜␜entity:media␝thumbnail␞␟src_with_alternate_widths',
          ],
          'adapter_matches_field_type' => [],
          'adapter_matches_instance' => [],
        ],
        // @todo Update \Drupal\sdc\Component\ComponentValidator to disallow this — does not make sense for presenting information?
        'optional, type=string&format=json-pointer' => [
          'SDC props' => [
            '⿲sdc_test_all_props:all-props␟test_string_format_' . str_replace('-', '_', JsonSchemaStringFormat::JSON_POINTER->value),
          ],
          'static prop source' => NULL,
          'instances' => [],
          'adapter_matches_field_type' => [],
          'adapter_matches_instance' => [],
        ],
        // @todo Update \Drupal\sdc\Component\ComponentValidator to disallow this — does not make sense for presenting information?
        'optional, type=string&format=regex' => [
          'SDC props' => [
            '⿲sdc_test_all_props:all-props␟test_string_format_' . JsonSchemaStringFormat::REGEX->value,
          ],
          'static prop source' => NULL,
          'instances' => [],
          'adapter_matches_field_type' => [],
          'adapter_matches_instance' => [],
        ],
        // @todo Update \Drupal\sdc\Component\ComponentValidator to disallow this — does not make sense for presenting information?
        'optional, type=string&format=relative-json-pointer' => [
          'SDC props' => [
            '⿲sdc_test_all_props:all-props␟test_string_format_' . str_replace('-', '_', JsonSchemaStringFormat::RELATIVE_JSON_POINTER->value),
          ],
          'static prop source' => NULL,
          'instances' => [],
          'adapter_matches_field_type' => [],
          'adapter_matches_instance' => [],
        ],
        'optional, type=string&format=time' => [
          'SDC props' => [
            '⿲sdc_test_all_props:all-props␟test_string_format_' . JsonSchemaStringFormat::TIME->value,
          ],
          // @todo Adapter for @FieldType=timestamp -> `type:string,format=time`, @FieldType=datetime -> `type:string,format=time`
          'static prop source' => NULL,
          'instances' => [],
          'adapter_matches_field_type' => [],
          'adapter_matches_instance' => [],
        ],
        'optional, type=string&format=uri' => [
          'SDC props' => [
            '⿲sdc_test_all_props:all-props␟test_string_format_' . JsonSchemaStringFormat::URI->value,
          ],
          'static prop source' => 'ℹ︎link␟url',
          'instances' => [
            'ℹ︎␜entity:file␝uri␞␟url',
            'ℹ︎␜entity:file␝uri␞␟value',
            'ℹ︎␜entity:media:baby_videos␝field_media_video_file␞␟entity␜␜entity:file␝uri␞␟url',
            'ℹ︎␜entity:media:baby_videos␝field_media_video_file␞␟entity␜␜entity:file␝uri␞␟value',
            'ℹ︎␜entity:media:baby_videos␝thumbnail␞␟entity␜␜entity:file␝uri␞␟url',
            'ℹ︎␜entity:media:baby_videos␝thumbnail␞␟entity␜␜entity:file␝uri␞␟value',
            'ℹ︎␜entity:media:baby_videos␝thumbnail␞␟src_with_alternate_widths',
            'ℹ︎␜entity:media:vacation_videos␝field_media_video_file_1␞␟entity␜␜entity:file␝uri␞␟url',
            'ℹ︎␜entity:media:vacation_videos␝field_media_video_file_1␞␟entity␜␜entity:file␝uri␞␟value',
            'ℹ︎␜entity:media:vacation_videos␝thumbnail␞␟entity␜␜entity:file␝uri␞␟url',
            'ℹ︎␜entity:media:vacation_videos␝thumbnail␞␟entity␜␜entity:file␝uri␞␟value',
            'ℹ︎␜entity:media:vacation_videos␝thumbnail␞␟src_with_alternate_widths',
            'ℹ︎␜entity:node:foo␝field_silly_image␞␟entity␜␜entity:file␝uri␞␟url',
            'ℹ︎␜entity:node:foo␝field_silly_image␞␟entity␜␜entity:file␝uri␞␟value',
            'ℹ︎␜entity:node:foo␝field_silly_image␞␟src_with_alternate_widths',
            'ℹ︎␜entity:node:foo␝media_video_field␞␟entity␜␜entity:media␝thumbnail␞␟src_with_alternate_widths',
            'ℹ︎␜entity:xb_page␝image␞␟entity␜␜entity:media␝thumbnail␞␟src_with_alternate_widths',
          ],
          'adapter_matches_field_type' => [],
          'adapter_matches_instance' => [],
        ],
        'optional, type=string&format=uri-reference' => [
          'SDC props' => [
            '⿲sdc_test_all_props:all-props␟test_string_format_' . str_replace('-', '_', JsonSchemaStringFormat::URI_REFERENCE->value),
          ],
          'static prop source' => 'ℹ︎link␟url',
          'instances' => [
            'ℹ︎␜entity:file␝uri␞␟url',
            'ℹ︎␜entity:file␝uri␞␟value',
            'ℹ︎␜entity:media:baby_videos␝field_media_video_file␞␟entity␜␜entity:file␝uri␞␟url',
            'ℹ︎␜entity:media:baby_videos␝field_media_video_file␞␟entity␜␜entity:file␝uri␞␟value',
            'ℹ︎␜entity:media:baby_videos␝thumbnail␞␟entity␜␜entity:file␝uri␞␟url',
            'ℹ︎␜entity:media:baby_videos␝thumbnail␞␟entity␜␜entity:file␝uri␞␟value',
            'ℹ︎␜entity:media:baby_videos␝thumbnail␞␟src_with_alternate_widths',
            'ℹ︎␜entity:media:vacation_videos␝field_media_video_file_1␞␟entity␜␜entity:file␝uri␞␟url',
            'ℹ︎␜entity:media:vacation_videos␝field_media_video_file_1␞␟entity␜␜entity:file␝uri␞␟value',
            'ℹ︎␜entity:media:vacation_videos␝thumbnail␞␟entity␜␜entity:file␝uri␞␟url',
            'ℹ︎␜entity:media:vacation_videos␝thumbnail␞␟entity␜␜entity:file␝uri␞␟value',
            'ℹ︎␜entity:media:vacation_videos␝thumbnail␞␟src_with_alternate_widths',
            'ℹ︎␜entity:node:foo␝field_silly_image␞␟entity␜␜entity:file␝uri␞␟url',
            'ℹ︎␜entity:node:foo␝field_silly_image␞␟entity␜␜entity:file␝uri␞␟value',
            'ℹ︎␜entity:node:foo␝field_silly_image␞␟src_with_alternate_widths',
            'ℹ︎␜entity:node:foo␝media_video_field␞␟entity␜␜entity:media␝thumbnail␞␟src_with_alternate_widths',
            'ℹ︎␜entity:xb_page␝image␞␟entity␜␜entity:media␝thumbnail␞␟src_with_alternate_widths',
          ],
          'adapter_matches_field_type' => [],
          'adapter_matches_instance' => [],
        ],
        // @todo Update \Drupal\sdc\Component\ComponentValidator to disallow this — does not make sense for presenting information?
        'optional, type=string&format=uri-template' => [
          'SDC props' => [
            '⿲sdc_test_all_props:all-props␟test_string_format_' . str_replace('-', '_', JsonSchemaStringFormat::URI_TEMPLATE->value),
          ],
          'static prop source' => NULL,
          'instances' => [],
          'adapter_matches_field_type' => [],
          'adapter_matches_instance' => [],
        ],
        'optional, type=string&format=uri-template&x-required-variables[0]=width' => [
          'SDC props' => [
            0 => '⿲xb_test_sdc:image-srcset-candidate-template-uri␟srcSetCandidateTemplate',
          ],
          'static prop source' => NULL,
          'instances' => [
            0 => 'ℹ︎␜entity:media:baby_videos␝thumbnail␞␟srcset_candidate_uri_template',
            1 => 'ℹ︎␜entity:media:vacation_videos␝thumbnail␞␟srcset_candidate_uri_template',
            2 => 'ℹ︎␜entity:node:foo␝field_silly_image␞␟srcset_candidate_uri_template',
            3 => 'ℹ︎␜entity:node:foo␝media_video_field␞␟entity␜␜entity:media␝thumbnail␞␟srcset_candidate_uri_template',
            4 => 'ℹ︎␜entity:xb_page␝image␞␟entity␜␜entity:media␝thumbnail␞␟srcset_candidate_uri_template',
          ],
          'adapter_matches_field_type' => [],
          'adapter_matches_instance' => [],
        ],
        'optional, type=string&format=uuid' => [
          'SDC props' => [
            '⿲sdc_test_all_props:all-props␟test_string_format_' . JsonSchemaStringFormat::UUID->value,
          ],
          'static prop source' => NULL,
          'instances' => [
            'ℹ︎␜entity:file␝uid␞␟entity␜␜entity:user␝uuid␞␟value',
            'ℹ︎␜entity:file␝uid␞␟target_uuid',
            'ℹ︎␜entity:file␝uuid␞␟value',
            'ℹ︎␜entity:media:baby_videos␝field_media_video_file␞␟entity␜␜entity:file␝uid␞␟target_uuid',
            'ℹ︎␜entity:media:baby_videos␝field_media_video_file␞␟entity␜␜entity:file␝uuid␞␟value',
            'ℹ︎␜entity:media:baby_videos␝revision_user␞␟entity␜␜entity:user␝uuid␞␟value',
            'ℹ︎␜entity:media:baby_videos␝revision_user␞␟target_uuid',
            'ℹ︎␜entity:media:baby_videos␝thumbnail␞␟entity␜␜entity:file␝uid␞␟target_uuid',
            'ℹ︎␜entity:media:baby_videos␝thumbnail␞␟entity␜␜entity:file␝uuid␞␟value',
            'ℹ︎␜entity:media:baby_videos␝uid␞␟entity␜␜entity:user␝uuid␞␟value',
            'ℹ︎␜entity:media:baby_videos␝uid␞␟target_uuid',
            'ℹ︎␜entity:media:baby_videos␝uuid␞␟value',
            'ℹ︎␜entity:media:vacation_videos␝field_media_video_file_1␞␟entity␜␜entity:file␝uid␞␟target_uuid',
            'ℹ︎␜entity:media:vacation_videos␝field_media_video_file_1␞␟entity␜␜entity:file␝uuid␞␟value',
            'ℹ︎␜entity:media:vacation_videos␝revision_user␞␟entity␜␜entity:user␝uuid␞␟value',
            'ℹ︎␜entity:media:vacation_videos␝revision_user␞␟target_uuid',
            'ℹ︎␜entity:media:vacation_videos␝thumbnail␞␟entity␜␜entity:file␝uid␞␟target_uuid',
            'ℹ︎␜entity:media:vacation_videos␝thumbnail␞␟entity␜␜entity:file␝uuid␞␟value',
            'ℹ︎␜entity:media:vacation_videos␝uid␞␟entity␜␜entity:user␝uuid␞␟value',
            'ℹ︎␜entity:media:vacation_videos␝uid␞␟target_uuid',
            'ℹ︎␜entity:media:vacation_videos␝uuid␞␟value',
            'ℹ︎␜entity:node:foo␝field_silly_image␞␟entity␜␜entity:file␝uid␞␟target_uuid',
            'ℹ︎␜entity:node:foo␝field_silly_image␞␟entity␜␜entity:file␝uuid␞␟value',
            'ℹ︎␜entity:node:foo␝media_video_field␞␟entity␜␜entity:media␝revision_user␞␟target_uuid',
            'ℹ︎␜entity:node:foo␝media_video_field␞␟entity␜␜entity:media␝uid␞␟target_uuid',
            'ℹ︎␜entity:node:foo␝media_video_field␞␟entity␜␜entity:media␝uuid␞␟value',
            'ℹ︎␜entity:node:foo␝media_video_field␞␟target_uuid',
            'ℹ︎␜entity:node:foo␝revision_uid␞␟entity␜␜entity:user␝uuid␞␟value',
            'ℹ︎␜entity:node:foo␝revision_uid␞␟target_uuid',
            'ℹ︎␜entity:node:foo␝uid␞␟entity␜␜entity:user␝uuid␞␟value',
            'ℹ︎␜entity:node:foo␝uid␞␟target_uuid',
            'ℹ︎␜entity:node:foo␝uuid␞␟value',
            'ℹ︎␜entity:path_alias␝uuid␞␟value',
            'ℹ︎␜entity:user␝uuid␞␟value',
            'ℹ︎␜entity:xb_page␝image␞␟entity␜␜entity:media␝revision_user␞␟target_uuid',
            'ℹ︎␜entity:xb_page␝image␞␟entity␜␜entity:media␝uid␞␟target_uuid',
            'ℹ︎␜entity:xb_page␝image␞␟entity␜␜entity:media␝uuid␞␟value',
            'ℹ︎␜entity:xb_page␝image␞␟target_uuid',
            'ℹ︎␜entity:xb_page␝owner␞␟entity␜␜entity:user␝uuid␞␟value',
            'ℹ︎␜entity:xb_page␝owner␞␟target_uuid',
            'ℹ︎␜entity:xb_page␝revision_user␞␟entity␜␜entity:user␝uuid␞␟value',
            'ℹ︎␜entity:xb_page␝revision_user␞␟target_uuid',
            'ℹ︎␜entity:xb_page␝uuid␞␟value',
          ],
          'adapter_matches_field_type' => [],
          'adapter_matches_instance' => [],
        ],
      ],
    ];

    return $cases;
  }

}
