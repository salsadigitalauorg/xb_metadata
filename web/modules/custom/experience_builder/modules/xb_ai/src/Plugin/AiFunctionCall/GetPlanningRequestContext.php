<?php

namespace Drupal\xb_ai\Plugin\AiFunctionCall;

use Drupal\Core\Plugin\Context\ContextDefinition;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\ai\Attribute\FunctionCall;
use Drupal\ai\Base\FunctionCallBase;
use Drupal\ai\Service\FunctionCalling\ExecutableFunctionCallInterface;
use Drupal\ai\Service\FunctionCalling\FunctionCallInterface;
use Drupal\ai\Utility\ContextDefinitionNormalizer;
use Drupal\ai_agents\PluginInterfaces\AiAgentContextInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Yaml\Yaml;

/**
 * Plugin implementation to provide planning request context to LLM planning agent.
 */
#[FunctionCall(
  id: 'xb_ai:get_planning_request_context',
  function_name: 'get_planning_request_context',
  name: 'Get Planning Request Context',
  description: 'Retrieves the user request and complexity level for LLM-driven landing page planning.',
  group: 'planning_tools',
  module_dependencies: ['experience_builder'],
  context_definitions: [
    'user_request' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup("User Request"),
      description: new TranslatableMarkup("The user's original request to create a plan for."),
      required: TRUE
    ),
    'complexity_level' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup("Complexity Level"),
      description: new TranslatableMarkup("The complexity level (high/medium/low) of the request."),
      required: TRUE
    ),
  ],
)]
final class GetPlanningRequestContext extends FunctionCallBase implements ExecutableFunctionCallInterface, AiAgentContextInterface {

  /**
   * The request context data.
   *
   * @var array
   */
  protected array $requestContext = [];

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
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function execute(): void {
    $userRequest = $this->getContextValue('user_request');
    $complexityLevel = $this->getContextValue('complexity_level');
    
    $this->requestContext = [
      'user_request' => $userRequest,
      'complexity_level' => $complexityLevel,
      'timestamp' => date('Y-m-d H:i:s'),
      'instructions' => 'Analyze this request with your professional UX expertise and create a comprehensive landing page plan. Use your creativity and industry knowledge - you are not constrained by any templates or predetermined patterns.',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getReadableOutput(): string {
    return Yaml::dump($this->requestContext, 10, 2);
  }

}