<?php

declare(strict_types=1);

namespace Drupal\Tests\experience_builder\Traits;

use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;

/**
 * Any test using these test cases must install the `xb_test_sdc` module.
 */
trait SingleDirectoryComponentTreeTestTrait {

  public const string UUID_DYNAMIC_STATIC_CARD_2 = '9145b0da-85a1-4ee7-ad1d-b1b63614aed6';
  public const string UUID_DYNAMIC_STATIC_CARD_3 = 'dab1145b-c5d5-4779-9be8-0a41c2d8ed29';
  public const string UUID_DYNAMIC_STATIC_CARD_4 = '09de669f-b85b-40ef-9c01-b27f1b089020';

  protected function createComponentTreeField(string $entity_type_id, string $bundle): void {
    $field_storage = FieldStorageConfig::create([
      'field_name' => 'field_component_tree',
      'entity_type' => $entity_type_id,
      'type' => 'component_tree',
    ]);
    $field_storage->save();

    FieldConfig::create([
      'field_storage' => $field_storage,
      'bundle' => $bundle,
    ])->save();
  }

  protected static function getValidTreeTestCases(): array {
    return [
      'valid values using static inputs' => [
        [
          [
            'uuid' => self::UUID_DYNAMIC_STATIC_CARD_2,
            'component_id' => 'sdc.xb_test_sdc.props-slots',
            'component_version' => 'ab4d3ddce315cf64',
            'inputs' => [
              'heading' => [
                'sourceType' => 'static:field_item:string',
                'value' => 'They say I am static, but I want to believe I can change!',
                'expression' => 'ℹ︎string␟value',
              ],
            ],
          ],
        ],
      ],
      'valid values for propless component' => [
        [
          [
            "uuid" => 'd0fb26bf-bc83-428c-a4bb-bea5ea43ffe7',
            "component_id" => "sdc.xb_test_sdc.druplicon",
            'component_version' => '8fe3be948e0194e1',
            'inputs' => [],
          ],
        ],

      ],
      'valid value for optional explicit input using an URL prop shape, with default value' => [
        [
          [
            'uuid' => '993cf84a-df55-41c6-bda9-a8bb616a48d0',
            'component_id' => 'sdc.xb_test_sdc.image-optional-with-example-and-additional-prop',
            'component_version' => '602623740c98a6cf',
            'inputs' => [
              'heading' => [
                'sourceType' => 'static:field_item:string',
                'value' => 'Gracie says hi!',
                'expression' => 'ℹ︎string␟value',
              ],
              'image' => [
                'sourceType' => 'default-relative-url',
                'value' => [
                  'src' => 'gracie.jpg',
                  'alt' => 'A good dog',
                  'width' => 601,
                  'height' => 402,
                ],
                'jsonSchema' => [
                  'type' => 'object',
                  'properties' => [
                    'src' => [
                      'type' => 'string',
                      'format' => 'uri-reference',
                      'pattern' => '^(/|https?://)?.*\.([Pp][Nn][Gg]|[Gg][Ii][Ff]|[Jj][Pp][Gg]|[Jj][Pp][Ee][Gg]|[Ww][Ee][Bb][Pp]|[Aa][Vv][Ii][Ff])(\?.*)?(#.*)?$',
                    ],
                    'alt' => ['type' => 'string'],
                    'width' => ['type' => 'integer'],
                    'height' => ['type' => 'integer'],
                  ],
                  'required' => ['src'],
                ],
                'componentId' => 'sdc.xb_test_sdc.image-optional-with-example-and-additional-prop',
              ],
            ],
          ],
        ],
      ],
    ];
  }

  protected static function getInvalidTreeTestCases(): array {
    return [
      'invalid values using dynamic inputs' => [
        [
          [
            'uuid' => 'd0aee529-89d9-4a47-8d59-7deb1817f952',
            'component_id' => 'sdc.xb_test_sdc.props-slots',
            'component_version' => 'ab4d3ddce315cf64',
            'inputs' => [
              'heading' => [
                'sourceType' => 'dynamic',
                'expression' => 'ℹ︎␜entity:node:article␝title␞␟value',
              ],
            ],
          ],
        ],
      ],
      'invalid UUID, missing component_id key' => [
        [
          ['uuid' => 'other-uuid'],
        ],
      ],
      'missing components, using dynamic inputs' => [
        [
          [
            'uuid' => self::UUID_DYNAMIC_STATIC_CARD_2,
            'component_id' => 'sdc.sdc_test.missing',
            'component_version' => 'irrelevant',
            'inputs' => [
              'heading' => [
                'sourceType' => 'dynamic',
                'expression' => 'ℹ︎␜entity:node:article␝title␞␟value',
              ],
            ],
          ],
          [
            'uuid' => self::UUID_DYNAMIC_STATIC_CARD_3,
            'component_id' => 'sdc.sdc_test.missing-also',
            'component_version' => 'irrelevant',
            'inputs' => [
              'heading' => [
                'sourceType' => 'dynamic',
                'expression' => 'ℹ︎␜entity:node:article␝title␞␟value',
              ],
            ],
          ],
          [
            'uuid' => self::UUID_DYNAMIC_STATIC_CARD_4,
            'component_id' => 'sdc.xb_test_sdc.props-slots',
            'component_version' => 'ab4d3ddce315cf64',
            'inputs' => [
              'heading' => [
                'sourceType' => 'dynamic',
                'expression' => 'ℹ︎␜entity:node:article␝title␞␟value',
              ],
            ],
          ],
        ],
      ],
      'missing components, using only static inputs' => [
        [
          [
            'uuid' => '6f0df1b5-cb78-4bfc-b403-400d24c4d655',
            'component_id' => 'sdc.sdc_test.missing',
            'component_version' => 'does not matter',
            'inputs' => [
              'text' => [
                'sourceType' => 'static:field_item:link',
                'value' => [
                  'uri' => 'https://drupal.org',
                  'title' => NULL,
                  'options' => [],
                ],
                'expression' => 'ℹ︎link␟url',
              ],
            ],
          ],
        ],
      ],
      'inputs invalid, using dynamic inputs' => [
        [
          [
            'uuid' => self::UUID_DYNAMIC_STATIC_CARD_2,
            'component_id' => 'sdc.xb_test_sdc.props-slots',
            'component_version' => 'ab4d3ddce315cf64',
            'inputs' => [
              'heading-2' => [
                'sourceType' => 'dynamic',
                'expression' => 'ℹ︎␜entity:node:article␝title␞␟value',
              ],
            ],
          ],
          [
            'uuid' => self::UUID_DYNAMIC_STATIC_CARD_3,
            'component_id' => 'sdc.xb_test_sdc.props-slots',
            'component_version' => 'ab4d3ddce315cf64',
            'inputs' => [
              'heading-1' => [
                'sourceType' => 'dynamic',
                'expression' => 'ℹ︎␜entity:node:article␝title␞␟value',
              ],
            ],
          ],
          [
            'uuid' => self::UUID_DYNAMIC_STATIC_CARD_4,
            'component_id' => 'sdc.xb_test_sdc.props-slots',
            'component_version' => 'ab4d3ddce315cf64',
            'inputs' => [
              'heading' => [
                'sourceType' => 'dynamic',
                'expression' => 'ℹ︎␜entity:node:article␝title␞␟value',
              ],
            ],
          ],
        ],
      ],
      'inputs invalid, using only static inputs' => [
        [
          [
            'uuid' => self::UUID_DYNAMIC_STATIC_CARD_2,
            'component_id' => 'sdc.xb_test_sdc.props-no-slots',
            'component_version' => '95f4f1d5ee47663b',
            'inputs' => [
              'heading-x' => [
                'sourceType' => 'static:field_item:link',
                'value' => [
                  'uri' => 'https://drupal.org',
                  'title' => NULL,
                  'options' => [],
                ],
                'expression' => 'ℹ︎link␟url',
              ],
            ],
          ],
        ],
      ],
      'missing inputs key' => [
        [
          [
            'uuid' => self::UUID_DYNAMIC_STATIC_CARD_2,
            'component_id' => 'sdc.xb_test_sdc.props-slots',
            'component_version' => 'ab4d3ddce315cf64',
          ],
          [
            'uuid' => self::UUID_DYNAMIC_STATIC_CARD_3,
            'component_id' => 'sdc.xb_test_sdc.props-slots',
            'component_version' => 'ab4d3ddce315cf64',
          ],
          [
            'uuid' => self::UUID_DYNAMIC_STATIC_CARD_4,
            'component_id' => 'sdc.xb_test_sdc.props-slots',
            'component_version' => 'ab4d3ddce315cf64',
          ],
        ],
      ],
      'non unique uuids' => [
        [
          [
            'uuid' => self::UUID_DYNAMIC_STATIC_CARD_4,
            'component_id' => 'sdc.xb_test_sdc.props-slots',
            'component_version' => 'ab4d3ddce315cf64',
            'inputs' => [
              'heading' => [
                'sourceType' => 'static:field_item:string',
                'value' => 'Shake dreams from your hair, my pretty child',
                'expression' => 'ℹ︎string␟value',
              ],
            ],
          ],
          [
            'uuid' => 'fa9ff0a8-e23a-492a-ab14-5460611fa2c1',
            'component_id' => 'sdc.xb_test_sdc.props-slots',
            'component_version' => 'ab4d3ddce315cf64',
            'inputs' => [
              'heading' => [
                'sourceType' => 'static:field_item:string',
                'value' => 'And we laugh like soft, mad children',
                'expression' => 'ℹ︎string␟value',
              ],
            ],
          ],
          [
            'uuid' => self::UUID_DYNAMIC_STATIC_CARD_4,
            'component_id' => 'sdc.xb_test_sdc.props-slots',
            'component_version' => 'ab4d3ddce315cf64',
            'inputs' => [
              'heading' => [
                'sourceType' => 'static:field_item:string',
                'value' => 'A vast radiant beach and cooled jewelled moon',
                'expression' => 'ℹ︎string␟value',
              ],
            ],
          ],
        ],
      ],
      'invalid parent' => [
        [
          [
            'uuid' => 'fa9ff0a8-e23a-492a-ab14-5460611fa2c1',
            'component_id' => 'sdc.xb_test_sdc.props-slots',
            'component_version' => 'ab4d3ddce315cf64',
            'inputs' => [
              'heading' => [
                'sourceType' => 'static:field_item:string',
                'value' => 'And we laugh like soft, mad children',
                'expression' => 'ℹ︎string␟value',
              ],
            ],
          ],
          [
            'uuid' => 'e303dd88-9409-4dc7-8a8b-a31602884a94',
            'slot' => 'the_body',
            'parent_uuid' => '6381352f-5b0a-4ca1-960d-a5505b37b27c',
            'component_id' => 'sdc.xb_test_sdc.props-slots',
            'component_version' => 'ab4d3ddce315cf64',
            'inputs' => [
              'heading' => [
                'sourceType' => 'static:field_item:string',
                'value' => ' Smug in the wooly cotton brains of infancy',
                'expression' => 'ℹ︎string␟value',
              ],
            ],
          ],
        ],
      ],
      'invalid slot' => [
        [
          [
            'uuid' => 'fa9ff0a8-e23a-492a-ab14-5460611fa2c1',
            'component_id' => 'sdc.xb_test_sdc.props-slots',
            'component_version' => 'ab4d3ddce315cf64',
            'inputs' => [
              'heading' => [
                'sourceType' => 'static:field_item:string',
                'value' => 'And we laugh like soft, mad children',
                'expression' => 'ℹ︎string␟value',
              ],
            ],
          ],
          [
            'uuid' => 'e303dd88-9409-4dc7-8a8b-a31602884a94',
            'slot' => 'banana',
            'parent_uuid' => 'fa9ff0a8-e23a-492a-ab14-5460611fa2c1',
            'component_version' => 'ab4d3ddce315cf64',
            'component_id' => 'sdc.xb_test_sdc.props-slots',
            'inputs' => [
              'heading' => [
                'sourceType' => 'static:field_item:string',
                'value' => ' Smug in the wooly cotton brains of infancy',
                'expression' => 'ℹ︎string␟value',
              ],
            ],
          ],
        ],
      ],
    ];
  }

}
