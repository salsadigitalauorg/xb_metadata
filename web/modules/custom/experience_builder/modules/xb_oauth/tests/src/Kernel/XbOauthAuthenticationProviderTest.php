<?php

declare(strict_types=1);

namespace Drupal\Tests\xb_oauth\Kernel;

use Drupal\Core\Routing\RouteObjectInterface;
use Drupal\KernelTests\KernelTestBase;
use Drupal\experience_builder\Entity\AssetLibrary;
use Drupal\experience_builder\Entity\JavaScriptComponent;
use Drupal\experience_builder\Entity\Pattern;
use Drupal\xb_oauth\Authentication\Provider\XbOauthAuthenticationProvider;
use Symfony\Component\HttpFoundation\InputBag;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Route;

/**
 * Tests the XB OAuth authentication provider.
 *
 * @coversDefaultClass \Drupal\xb_oauth\Authentication\Provider\XbOauthAuthenticationProvider
 * @group xb_oauth
 */
class XbOauthAuthenticationProviderTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'experience_builder',
    'media',
    'xb_oauth',
    'simple_oauth',
    'serialization',
    'user',
  ];

  /**
   * The authentication provider being tested.
   *
   * @var \Drupal\xb_oauth\Authentication\Provider\XbOauthAuthenticationProvider
   */
  protected XbOauthAuthenticationProvider $authProvider;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installConfig('system');
    $this->authProvider = $this->container->get(XbOauthAuthenticationProvider::class);
  }

  /**
   * Data provider for testing authentication provider logic on all XB routes.
   *
   * @return array<int, array{
   *   0: string,
   *   1: array<string>,
   *   2: bool
   *   }> Array of test cases where:
   *   - Index 0: Route name
   *   - Index 1: Route parameters
   *   - Index 2: Expected result of applies() method
   */
  public static function dataProviderRoutes(): array {
    return [
      ['entity.component.audit', [], FALSE],
      ['entity.component.delete_form', [], FALSE],
      ['entity.component.disable', [], FALSE],
      ['entity.component.enable', [], FALSE],
      ['experience_builder.api.auto-save.get', [], FALSE],
      ['experience_builder.api.auto-save.post', [], FALSE],
      ['experience_builder.api.config.auto-save.get', [], FALSE],
      ['experience_builder.api.config.auto-save.get.css', [], FALSE],
      ['experience_builder.api.config.auto-save.get.js', [], FALSE],
      ['experience_builder.api.config.auto-save.patch', [], FALSE],
      ['experience_builder.api.config.delete', ['xb_config_entity_type_id' => JavaScriptComponent::ENTITY_TYPE_ID], TRUE],
      ['experience_builder.api.config.delete', ['xb_config_entity_type_id' => Pattern::ENTITY_TYPE_ID], FALSE],
      ['experience_builder.api.config.delete', ['xb_config_entity_type_id' => AssetLibrary::ENTITY_TYPE_ID], TRUE],
      ['experience_builder.api.config.delete', ['xb_config_entity_type_id' => 'non-existent'], FALSE],
      ['experience_builder.api.config.delete', [], FALSE],
      ['experience_builder.api.config.get', ['xb_config_entity_type_id' => JavaScriptComponent::ENTITY_TYPE_ID], TRUE],
      ['experience_builder.api.config.get', ['xb_config_entity_type_id' => Pattern::ENTITY_TYPE_ID], FALSE],
      ['experience_builder.api.config.get', ['xb_config_entity_type_id' => AssetLibrary::ENTITY_TYPE_ID], TRUE],
      ['experience_builder.api.config.get', ['xb_config_entity_type_id' => 'non-existent'], FALSE],
      ['experience_builder.api.config.get', [], FALSE],
      ['experience_builder.api.config.list', ['xb_config_entity_type_id' => JavaScriptComponent::ENTITY_TYPE_ID], TRUE],
      ['experience_builder.api.config.list', ['xb_config_entity_type_id' => Pattern::ENTITY_TYPE_ID], FALSE],
      ['experience_builder.api.config.list', ['xb_config_entity_type_id' => AssetLibrary::ENTITY_TYPE_ID], TRUE],
      ['experience_builder.api.config.list', ['xb_config_entity_type_id' => 'non-existent'], FALSE],
      ['experience_builder.api.config.list', [], FALSE],
      ['experience_builder.api.config.patch', ['xb_config_entity_type_id' => JavaScriptComponent::ENTITY_TYPE_ID], TRUE],
      ['experience_builder.api.config.patch', ['xb_config_entity_type_id' => Pattern::ENTITY_TYPE_ID], FALSE],
      ['experience_builder.api.config.patch', ['xb_config_entity_type_id' => AssetLibrary::ENTITY_TYPE_ID], TRUE],
      ['experience_builder.api.config.patch', ['xb_config_entity_type_id' => 'non-existent'], FALSE],
      ['experience_builder.api.config.patch', [], FALSE],
      ['experience_builder.api.config.post', ['xb_config_entity_type_id' => JavaScriptComponent::ENTITY_TYPE_ID], TRUE],
      ['experience_builder.api.config.post', ['xb_config_entity_type_id' => Pattern::ENTITY_TYPE_ID], FALSE],
      ['experience_builder.api.config.post', ['xb_config_entity_type_id' => AssetLibrary::ENTITY_TYPE_ID], TRUE],
      ['experience_builder.api.config.post', ['xb_config_entity_type_id' => 'non-existent'], FALSE],
      ['experience_builder.api.config.post', [], FALSE],
      ['experience_builder.api.content.create', [], FALSE],
      ['experience_builder.api.content.delete', [], FALSE],
      ['experience_builder.api.content.list', [], FALSE],
      ['experience_builder.api.form.component_inputs', [], FALSE],
      ['experience_builder.api.form.content_entity', [], FALSE],
      ['experience_builder.api.layout.get', [], FALSE],
      ['experience_builder.api.layout.patch', [], FALSE],
      ['experience_builder.api.layout.post', [], FALSE],
      ['experience_builder.api.log_error', [], FALSE],
      ['experience_builder.component.status', [], FALSE],
      ['experience_builder.experience_builder', [], FALSE],
    ];
  }

  /**
   * Tests whether the authentication provider applies to a route.
   *
   * @dataProvider dataProviderRoutes
   * @covers ::applies
   */
  public function testApplies(string $route_name, array $parameters, bool $expected_apply): void {
    $route = new Route($this->container->get('router.route_provider')->getRouteByName($route_name)->getPath());
    $request = new Request();
    $request->attributes->set(RouteObjectInterface::ROUTE_NAME, $route_name);
    $request->attributes->set(RouteObjectInterface::ROUTE_OBJECT, $route);
    $request->attributes->set('_raw_variables', new InputBag($parameters));

    $this->assertFalse(
      $this->authProvider->applies($request),
      'The authentication provider should NOT apply without an access token.'
    );

    $request->headers->set('Authorization', 'Bearer token-123');
    $this->assertEquals(
      $this->authProvider->applies($request),
      $expected_apply,
      $expected_apply ? 'The authentication provider should apply' : 'The authentication provider should NOT apply'
    );
  }

}
