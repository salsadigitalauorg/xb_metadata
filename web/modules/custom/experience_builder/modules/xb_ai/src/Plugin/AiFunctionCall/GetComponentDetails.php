<?php

namespace Drupal\xb_ai\Plugin\AiFunctionCall;

use Drupal\Core\Plugin\Context\ContextDefinition;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\ai\Attribute\FunctionCall;
use Drupal\ai\Base\FunctionCallBase;
use Drupal\ai\Service\FunctionCalling\ExecutableFunctionCallInterface;
use Drupal\ai\Service\FunctionCalling\FunctionCallInterface;
use Drupal\ai\Utility\ContextDefinitionNormalizer;
use Drupal\xb_ai\XbAiPageBuilderHelper;
use Drupal\xb_ai\Service\XbAiComponentSchemaManager;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Symfony\Component\Yaml\Yaml;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;

/**
 * Function call plugin to get detailed component specifications on-demand.
 *
 * This plugin retrieves detailed specifications (props, slots, enums) for
 * specific components by ID. Enables just-in-time context loading for build
 * agents, supporting the two-stage loading optimization strategy.
 *
 * @internal
 */
#[FunctionCall(
  id: 'xb_ai:get_component_details',
  function_name: 'get_component_details',
  name: 'Get Component Details',
  description: 'Load detailed specifications for specific components on-demand. Provide component IDs to get full props, slots, and enum definitions.',
  group: 'information_tools',
  module_dependencies: ['xb_ai'],
  context_definitions: [
    'component_ids' => new ContextDefinition(
      data_type: 'list',
      label: new TranslatableMarkup("Component IDs"),
      description: new TranslatableMarkup("The IDs of the components to retrieve details for."),
      required: TRUE,
    )
  ],
)]
final class GetComponentDetails extends FunctionCallBase implements ExecutableFunctionCallInterface {

  /**
   * The XB page builder helper service.
   *
   * @var \Drupal\xb_ai\XbAiPageBuilderHelper
   */
  protected XbAiPageBuilderHelper $pageBuilderHelper;

  /**
   * The component schema manager service.
   *
   * @var \Drupal\xb_ai\Service\XbAiComponentSchemaManager
   */
  protected XbAiComponentSchemaManager $schemaManager;

  /**
   * The logger factory.
   *
   * @var \Drupal\Core\Logger\LoggerChannelFactoryInterface
   */
  protected LoggerChannelFactoryInterface $loggerFactory;

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
    $instance->loggerFactory = $container->get('logger.factory');
    $instance->pageBuilderHelper = $container->get('xb_ai.page_builder_helper');
    $instance->schemaManager = $container->get('xb_ai.component_schema_manager');
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

    $component_ids = $this->getContextValue('component_ids');
    $this->loggerFactory->get('xb_ai')->info('Retrieving component details for IDs: @ids', ['@ids' => implode(', ', $component_ids)]);

    // Validate input
    if (empty($component_ids) || !is_array($component_ids)) {
      throw new \Exception('component_ids parameter is required and must be an array of component IDs.');
    }

    try {
      // Use the efficient schema manager instead of the page builder helper
      $component_details = $this->schemaManager->getMultipleSchemas($component_ids);
      
      // Check for missing components
      $missing_components = array_diff($component_ids, array_keys($component_details));
      if (!empty($missing_components)) {
        $this->loggerFactory->get('xb_ai')->warning('Component schemas not found for: @missing', ['@missing' => implode(', ', $missing_components)]);
        
        // For backward compatibility, try to fall back to page builder helper for missing components
        try {
          $fallback_details = $this->pageBuilderHelper->getComponentDetailsForAi($missing_components);
          $component_details = array_merge($component_details, $fallback_details);
          $this->loggerFactory->get('xb_ai')->info('Retrieved @count missing components via fallback', ['@count' => count($fallback_details)]);
        } catch (\Exception $fallback_error) {
          $this->loggerFactory->get('xb_ai')->warning('Fallback also failed for missing components: @error', ['@error' => $fallback_error->getMessage()]);
        }
      }

      if (empty($component_details)) {
        throw new \Exception('No component details could be retrieved for the requested IDs: ' . implode(', ', $component_ids));
      }

      $this->setOutput(Yaml::dump($component_details, 6, 2));
      $this->loggerFactory->get('xb_ai')->info('Successfully retrieved details for @count components using schema manager', ['@count' => count($component_details)]);
      
    } catch (\Exception $e) {
      $this->loggerFactory->get('xb_ai')->error('Error loading component details: @error', ['@error' => $e->getMessage()]);
      throw new \Exception('Error loading component details: ' . $e->getMessage());
    }
  }

}
