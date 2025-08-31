<?php

namespace Drupal\xb_ai\Plugin\AiFunctionCall;

use Drupal\ai\Attribute\FunctionCall;
use Drupal\ai\Base\FunctionCallBase;
use Drupal\ai\Service\FunctionCalling\ExecutableFunctionCallInterface;
use Drupal\ai\Service\FunctionCalling\FunctionCallInterface;
use Drupal\ai\Utility\ContextDefinitionNormalizer;
use Drupal\ai_agents\PluginInterfaces\AiAgentContextInterface;
use Drupal\xb_ai\XbAiPageBuilderHelper;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Session\AccountProxyInterface;

/**
 * Function call plugin to get lightweight component catalog.
 *
 * This plugin retrieves minimal component information (id, name, description,
 * group) to enable intelligent component selection without overwhelming the LLM
 * with detailed specifications. Provides 90% context reduction (48KB+ → ~5KB).
 *
 * @internal
 */
#[FunctionCall(
  id: 'xb_ai:get_component_light_catalog',
  function_name: 'get_component_light_catalog',
  name: 'Get Component Light Catalog',
  description: 'Get lightweight component catalog with minimal info (id, name, description, group) for efficient component discovery and planning.',
  group: 'information_tools',
  module_dependencies: ['xb_ai'],
)]
final class GetComponentLightCatalog extends FunctionCallBase implements ExecutableFunctionCallInterface, AiAgentContextInterface {

  /**
   * The XB page builder helper service.
   *
   * @var \Drupal\xb_ai\XbAiPageBuilderHelper
   */
  protected XbAiPageBuilderHelper $pageBuilderHelper;

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected AccountProxyInterface $currentUser;

  /**
   * Load from dependency injection container.
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): FunctionCallInterface | static {
    $instance = new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      new ContextDefinitionNormalizer(),
    );
    $instance->pageBuilderHelper = $container->get('xb_ai.page_builder_helper');
    $instance->currentUser = $container->get('current_user');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function execute(): void {
    // Make sure that the user has the right permissions.
    if (!$this->currentUser->hasPermission('use experience builder ai')) {
      throw new \Exception('The current user does not have the right permissions to run this tool.');
    }
    $this->setOutput($this->pageBuilderHelper->getComponentLightCatalogForAi());
  }

}