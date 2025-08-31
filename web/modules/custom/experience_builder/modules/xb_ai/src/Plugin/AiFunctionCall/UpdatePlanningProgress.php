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
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\xb_ai\XbAiTempStore;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Yaml\Yaml;

/**
 * Function call plugin to update planning progress and mark tasks completed.
 *
 * This plugin allows agents to mark milestone tasks as completed and
 * track progress through multi-step landing page creation workflows.
 */
#[FunctionCall(
  id: 'xb_ai:update_planning_progress',
  function_name: 'update_planning_progress',
  name: 'Update Planning Progress',
  description: 'Updates the progress of milestone tasks in the current landing page plan.',
  group: 'planning_tools',
  context_definitions: [
    'task_id' => new ContextDefinition(
      data_type: 'integer',
      label: new TranslatableMarkup("Task ID"),
      description: new TranslatableMarkup("The ID of the task to update (1, 2, 3, etc.)."),
      required: TRUE
    ),
    'status' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup("Task Status"),
      description: new TranslatableMarkup("The new status for the task: 'pending', 'in_progress', or 'completed'."),
      required: TRUE
    ),
  ],
)]
final class UpdatePlanningProgress extends FunctionCallBase implements ExecutableFunctionCallInterface, AiAgentContextInterface {

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

    $taskId = (int) $this->getContextValue('task_id');
    $status = $this->getContextValue('status');

    // Validate status
    $validStatuses = ['pending', 'in_progress', 'completed'];
    if (!in_array($status, $validStatuses)) {
      throw new \InvalidArgumentException('Invalid status. Must be one of: ' . implode(', ', $validStatuses));
    }

    // Retrieve current planning context
    $planningContext = $this->xbAiTempStore->getData(XbAiTempStore::LANDING_PAGE_PLAN_KEY);
    
    if (!$planningContext) {
      $this->setOutput('No planning context found. Cannot update task progress.');
      return;
    }

    $planData = Yaml::parse($planningContext);
    
    // Find and update the specified task
    $taskFound = false;
    if (isset($planData['milestone_tasks'])) {
      foreach ($planData['milestone_tasks'] as &$task) {
        if ($task['task_id'] == $taskId) {
          $oldStatus = $task['status'];
          $task['status'] = $status;
          $taskFound = true;
          
          // Log the status change
          \Drupal::logger('xb_ai')->info('Planning task @task_id status changed from @old to @new', [
            '@task_id' => $taskId,
            '@old' => $oldStatus,
            '@new' => $status,
          ]);
          break;
        }
      }
    }

    if (!$taskFound) {
      $this->setOutput("Task ID {$taskId} not found in current planning context.");
      return;
    }

    // Save updated planning data back to tempstore
    $this->xbAiTempStore->setData(XbAiTempStore::LANDING_PAGE_PLAN_KEY, Yaml::dump($planData, 10, 2));

    // Generate summary output
    $remainingTasks = array_filter($planData['milestone_tasks'], function($task) {
      return $task['status'] === 'pending';
    });
    
    $completedTasks = array_filter($planData['milestone_tasks'], function($task) {
      return $task['status'] === 'completed';
    });

    $summary = [
      'task_updated' => $taskId,
      'new_status' => $status,
      'remaining_tasks' => count($remainingTasks),
      'completed_tasks' => count($completedTasks),
      'total_tasks' => count($planData['milestone_tasks']),
      'has_remaining_tasks' => !empty($remainingTasks),
    ];

    $this->setOutput(Yaml::dump($summary, 10, 2));
  }

}