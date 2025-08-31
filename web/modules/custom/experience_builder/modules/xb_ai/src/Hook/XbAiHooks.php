<?php

declare(strict_types=1);

namespace Drupal\xb_ai\Hook;

use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Hook implementations for xb_ai tokens.
 */
class XbAiHooks {
  use StringTranslationTrait;

  /**
   * Implements hook_token_info().
   */
  #[Hook('token_info')]
  public function xb_ai_token_info(): array {
    return [
      'types' => [
        'xb_ai' => [
          'name' => $this->t('XB AI Agent'),
          'description' => $this->t('Tokens related to XB AI Agent context.'),
        ],
      ],
      'tokens' => [
        'xb_ai' => [
          'entity_type' => [
            'name' => $this->t('Entity Type'),
            'description' => $this->t('Returns the entity type value passed to the AI Agent.'),
          ],
          'entity_id' => [
            'name' => $this->t('Entity Id'),
            'description' => $this->t('Returns the entity id value passed to the AI Agent.'),
          ],
          'selected_component' => [
            'name' => $this->t('Selected Component'),
            'description' => $this->t('Returns the selected component name passed to the AI Agent.'),
          ],
          'layout' => [
            'name' => $this->t('Layout'),
            'description' => $this->t('Returns the current page layout value passed to the AI Agent.'),
          ],
          'derived_proptypes' => [
            'name' => $this->t('derived Proptypes'),
            'description' => $this->t('Returns the proptypes available in experience builder.'),
          ],
          'page_title' => [
            'name' => $this->t('Page Title'),
            'description' => $this->t('Returns the title of the page.'),
          ],
          'page_description' => [
            'name' => $this->t('Page Description'),
            'description' => $this->t('Returns the description of the page.'),
          ],
          'active_component_uuid' => [
            'name' => $this->t('Active Component UUID'),
            'description' => $this->t('Returns the UUID of the active component in the page.'),
          ],
          'available_regions' => [
            'name' => $this->t('Available Regions'),
            'description' => $this->t('Returns the available regions in experience builder.'),
          ],
        ],
      ],
    ];
  }

  /**
   * Implements hook_tokens().
   */
  #[Hook('tokens')]
  public function xb_ai_tokens(string $type, array $tokens, array $data = [], array $options = []): array {
    $replacements = [];

    if ($type === 'xb_ai') {
      foreach ($tokens as $name => $original) {
        switch ($name) {
          case 'entity_type':
            $replacements[$original] = !empty($data['entity_type']) ? $data['entity_type'] : NULL;
            break;

          case 'entity_id':
            $replacements[$original] = !empty($data['entity_id']) ? $data['entity_id'] : NULL;
            break;

          case 'selected_component':
            $replacements[$original] = !empty($data['selected_component']) ? $data['selected_component'] : NULL;
            break;

          case 'layout':
            $replacements[$original] = !empty($data['layout']) ? $data['layout'] : NULL;
            break;

          case 'derived_proptypes':
            $replacements[$original] = !empty($data['derived_proptypes']) ? $data['derived_proptypes'] : NULL;
            break;

          case 'page_title':
            $replacements[$original] = !empty($data['page_title']) ? $data['page_title'] : NULL;
            break;

          case 'page_description':
            $replacements[$original] = !empty($data['page_description']) ? $data['page_description'] : NULL;
            break;

          case 'active_component_uuid':
            $replacements[$original] = !empty($data['active_component_uuid']) ? $data['active_component_uuid'] : 'None';
            break;

          case 'available_regions':
            $replacements[$original] = !empty($data['available_regions']) ? $data['available_regions'] : NULL;
            break;
        }
      }
    }

    return $replacements;
  }

}
