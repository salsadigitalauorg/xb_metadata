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
 * Function call plugin to get the current landing page planning context.
 *
 * This plugin retrieves planning information stored by the orchestrator
 * for use by specialized agents in executing planned tasks.
 */
#[FunctionCall(
  id: 'xb_ai:get_planning_context',
  function_name: 'get_planning_context',
  name: 'Get Planning Context',
  description: 'Gets the current landing page plan and context stored in the system.',
  group: 'planning_tools',
)]
final class GetPlanningContext extends FunctionCallBase implements ExecutableFunctionCallInterface, AiAgentContextInterface {

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
    
    if ($planningContext) {
      $planData = Yaml::parse($planningContext);
      
      // Enhance output with progress information
      $currentTask = $this->getCurrentTask($planData);
      $remainingTasks = $this->getRemainingTasks($planData);
      $completedCount = $this->getCompletedTaskCount($planData);
      
      // INFINITE LOOP PREVENTION: If no remaining tasks, do not return planning data
      // The agent should be calling get_current_layout instead
      if (empty($remainingTasks)) {
        $this->setOutput('ERROR: All tasks are completed. Agent should STOP immediately. Do not call get_planning_context when no tasks remain.');
        return;
      }
      
      $enhancedOutput = [
        'planning_data' => $planData,
        'current_task' => $currentTask,
        'remaining_tasks' => $remainingTasks,
        'progress' => [
          'completed' => $completedCount,
          'total' => count($planData['milestone_tasks'] ?? []),
          'has_remaining_tasks' => !empty($remainingTasks),
        ],
      ];
      
      $this->setOutput(Yaml::dump($enhancedOutput, 10, 2));
    } else {
      $this->setOutput('No planning context currently stored. Request may not require structured planning or planning has not been generated yet.');
    }
  }

  /**
   * Get the current task (first pending task).
   *
   * @param array $planData
   *   The planning data array.
   *
   * @return array|null
   *   The current task or NULL if no pending tasks.
   */
  private function getCurrentTask(array $planData): ?array {
    $tasks = $planData['milestone_tasks'] ?? [];
    foreach ($tasks as $task) {
      if ($task['status'] === 'pending') {
        return $task;
      }
    }
    return null;
  }

  /**
   * Get all remaining pending tasks.
   *
   * @param array $planData
   *   The planning data array.
   *
   * @return array
   *   Array of remaining pending tasks.
   */
  private function getRemainingTasks(array $planData): array {
    $tasks = $planData['milestone_tasks'] ?? [];
    return array_filter($tasks, function($task) {
      return $task['status'] === 'pending';
    });
  }

  /**
   * Get the count of completed tasks.
   *
   * @param array $planData
   *   The planning data array.
   *
   * @return int
   *   Number of completed tasks.
   */
  private function getCompletedTaskCount(array $planData): int {
    $tasks = $planData['milestone_tasks'] ?? [];
    return count(array_filter($tasks, function($task) {
      return $task['status'] === 'completed';
    }));
  }

}