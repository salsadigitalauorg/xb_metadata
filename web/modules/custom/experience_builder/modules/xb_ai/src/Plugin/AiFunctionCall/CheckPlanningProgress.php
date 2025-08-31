<?php

namespace Drupal\xb_ai\Plugin\AiFunctionCall;

use Drupal\ai\Attribute\FunctionCall;
use Drupal\ai\Base\FunctionCallBase;
use Drupal\ai\Service\FunctionCalling\ExecutableFunctionCallInterface;
use Drupal\ai\Service\FunctionCalling\FunctionCallInterface;
use Drupal\ai\Utility\ContextDefinitionNormalizer;
use Drupal\ai_agents\PluginInterfaces\AiAgentContextInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\xb_ai\XbAiTempStore;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Yaml\Yaml;

/**
 * Function call plugin to check planning progress and remaining tasks.
 *
 * This plugin is used by the agent loop control to determine if there are
 * remaining planned tasks that need to be executed before finishing.
 */
#[FunctionCall(
  id: 'xb_ai:check_planning_progress',
  function_name: 'check_planning_progress',
  name: 'Check Planning Progress',
  description: 'Checks if there are remaining planned tasks that need to be executed.',
  group: 'planning_tools',
)]
final class CheckPlanningProgress extends FunctionCallBase implements ExecutableFunctionCallInterface, AiAgentContextInterface {

  /**
   * The XB AI tempstore service.
   *
   * @var \Drupal\xb_ai\XbAiTempStore
   */
  protected XbAiTempStore $xbAiTempStore;

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
    $instance->xbAiTempStore = $container->get('xb_ai.tempstore');
    $instance->currentUser = $container->get('current_user');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function execute(): void {
    // Check permissions
    if (!$this->currentUser->hasPermission('use experience builder ai')) {
      throw new \Exception('The current user does not have the right permissions to run this tool.');
    }

    // Retrieve planning context from tempstore
    $planningContext = $this->xbAiTempStore->getData(XbAiTempStore::LANDING_PAGE_PLAN_KEY);
    
    if (!$planningContext) {
      $this->setOutput(Yaml::dump([
        'has_planning_context' => false,
        'has_remaining_tasks' => false,
        'should_continue' => false,
      ], 2, 2));
      return;
    }

    $planData = Yaml::parse($planningContext);
    
    // Count task statuses
    $tasks = $planData['milestone_tasks'] ?? [];
    $pendingTasks = array_filter($tasks, fn($task) => $task['status'] === 'pending');
    $inProgressTasks = array_filter($tasks, fn($task) => $task['status'] === 'in_progress');
    $completedTasks = array_filter($tasks, fn($task) => $task['status'] === 'completed');
    
    $hasRemainingTasks = !empty($pendingTasks) || !empty($inProgressTasks);
    
    $result = [
      'has_planning_context' => true,
      'has_remaining_tasks' => $hasRemainingTasks,
      'should_continue' => $hasRemainingTasks,
      'task_counts' => [
        'pending' => count($pendingTasks),
        'in_progress' => count($inProgressTasks),
        'completed' => count($completedTasks),
        'total' => count($tasks),
      ],
      'next_task' => !empty($pendingTasks) ? array_values($pendingTasks)[0] : null,
    ];

    // Add explicit termination instruction when no remaining tasks
    if (!$hasRemainingTasks) {
      $result['termination_instruction'] = 'All tasks completed. STOP immediately. Do not call any more tools.';
    }

    $this->setOutput(Yaml::dump($result, 10, 2));
  }

}