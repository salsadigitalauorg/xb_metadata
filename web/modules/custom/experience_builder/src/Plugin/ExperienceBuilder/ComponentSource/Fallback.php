<?php

declare(strict_types=1);

namespace Drupal\experience_builder\Plugin\ExperienceBuilder\ComponentSource;

use Drupal\Component\Utility\Html;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\experience_builder\Attribute\ComponentSource;
use Drupal\experience_builder\ComponentSource\ComponentSourceBase;
use Drupal\experience_builder\ComponentSource\ComponentSourceWithSlotsInterface;
use Drupal\experience_builder\Entity\Component;
use Drupal\experience_builder\Plugin\Field\FieldType\ComponentTreeItem;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Component\Validator\ConstraintViolationListInterface;

/**
 * Defines a fallback component source.
 */
#[ComponentSource(
  id: self::PLUGIN_ID,
  label: new TranslatableMarkup('Fallback'),
  supportsImplicitInputs: FALSE,
)]
final class Fallback extends ComponentSourceBase implements ComponentSourceWithSlotsInterface {
  public const string PLUGIN_ID = 'fallback';

  public function defaultConfiguration(): array {
    return parent::defaultConfiguration() + ['slots' => []];
  }

  public function getReferencedPluginClass(): ?string {
    return NULL;
  }

  public function getComponentDescription(): TranslatableMarkup {
    return new TranslatableMarkup('Fallback');
  }

  public function renderComponent(array $inputs, string $componentUuid, bool $isPreview): array {
    return [
      '#type' => 'inline_template',
      '#template' => '<div data-fallback="{{ component_uuid }}"></div>',
      '#context' => [
        'component_uuid' => $componentUuid,
        // Ensure our Twig node visitor can emit the required HTML comments
        // that allow the preview overlay to work.
        // @see \Drupal\experience_builder\Extension\XbWrapperNode
        // @see \Drupal\experience_builder\Extension\XbPropVisitor::enterNode
        'xb_uuid' => $componentUuid,
        'xb_is_preview' => $isPreview,
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  protected function getExplicitInputDefinitions(): array {
    return [];
  }

  public function requiresExplicitInput(): bool {
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function getDefaultExplicitInput(): array {
    return [];
  }

  public function getExplicitInput(string $uuid, ComponentTreeItem $item): array {
    return $item->getInputs() ?? [];
  }

  public function hydrateComponent(array $explicit_input): array {
    return [
      'slots' => array_map(fn($slot) => $slot['examples'][0] ?? '', $this->getSlotDefinitions()),
    ];
  }

  public function inputToClientModel(array $explicit_input): array {
    // Just keep things as is.
    return $explicit_input;
  }

  public function getClientSideInfo(Component $component): array {
    return [
      'source' => (string) new TranslatableMarkup('Fallback component'),
      'build' => $this->renderComponent([], $component->uuid(), FALSE),
      'metadata' => ['slots' => $this->getSlotDefinitions()],
      'field_data' => [],
      'transforms' => [],
    ];
  }

  public function buildConfigurationForm(array $form, FormStateInterface $form_state, ?Component $component = NULL, string $component_instance_uuid = '', array $client_model = [], ?EntityInterface $entity = NULL, array $settings = []): array {
    // @todo Improve this in https://drupal.org/i/3524299.
    $form['warning'] = [
      '#type' => 'html_tag',
      '#tag' => 'strong',
      '#value' => $this->t('Component has been deleted. Copy values to new component.'),
    ];
    $form['input'] = [
      '#type' => 'textarea',
      '#value' => \json_encode($client_model, \JSON_PRETTY_PRINT & \JSON_THROW_ON_ERROR),
      '#disabled' => TRUE,
      '#title' => $this->t('Previously stored input'),
    ];
    return $form;
  }

  public function clientModelToInput(string $component_instance_uuid, Component $component, array $client_model, ?ConstraintViolationListInterface $violations = NULL): array {
    // Just keep things as is.
    // @phpstan-ignore-next-line Array shape here is unknown.
    return $client_model;
  }

  public function validateComponentInput(array $inputValues, string $component_instance_uuid, ?FieldableEntityInterface $entity): ConstraintViolationListInterface {
    return new ConstraintViolationList();
  }

  public function checkRequirements(): void {
  }

  public function calculateDependencies(): array {
    return [];
  }

  public function validateConfigurationForm(array &$form, FormStateInterface $form_state): void {
  }

  public function submitConfigurationForm(array &$form, FormStateInterface $form_state): void {
  }

  public function getSlotDefinitions(): array {
    return $this->getConfiguration()['slots'] ?? [];
  }

  /**
   * {@inheritdoc}
   *
   * ⚠️ This doesn't render the contents of the slot, just the wrapper markup
   * to allow the UI to work.
   *
   * @todo Refactor in https://www.drupal.org/project/experience_builder/issues/3524047
   */
  public function setSlots(array &$build, array $slots): void {
    $build['#context'] += $slots;
    $slot_names = \array_keys($slots);
    // Add the slot ID metadata that triggers the Twig node visitor.
    // @see \Drupal\experience_builder\Extension\XbWrapperNode
    // @see \Drupal\experience_builder\Extension\XbPropVisitor::enterNode
    $build['#context']['xb_slot_ids'] = $slot_names;
    $build['#template'] = '<div data-fallback="{{ component_uuid }}">';
    foreach ($slot_names as $slot_name) {
      // Prevent XSS via malicious render array.
      $escaped_slot_name = Html::escape((string) $slot_name);
      // Print each slot by name. This ensures our Twig node visitor can emit
      // the required HTML comments that allow the slot overlay to work.
      // @see \Drupal\experience_builder\Extension\XbWrapperNode
      // @see \Drupal\experience_builder\Extension\XbPropVisitor::enterNode
      $build['#template'] .= \sprintf('{{ %s }}', $escaped_slot_name);
    }
    $build['#template'] .= '</div>';
  }

}
