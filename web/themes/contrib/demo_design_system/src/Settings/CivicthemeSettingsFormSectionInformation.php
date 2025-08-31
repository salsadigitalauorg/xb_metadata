<?php

declare(strict_types=1);

namespace Drupal\civictheme\Settings;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Link;
use Drupal\Core\Url;

/**
 * CivicTheme settings section to display additional information.
 */
class CivicthemeSettingsFormSectionInformation extends CivicthemeSettingsFormSectionBase {

  /**
   * {@inheritdoc}
   */
  public function weight(): int {
    return 20;
  }

  /**
   * {@inheritdoc}
   *
   * @SuppressWarnings(PHPMD.StaticAccess)
   */
  public function form(array &$form, FormStateInterface $form_state): void {
    $message = $this->t('<div class="messages messages--info">@documentation<br/>@design_system<br/>@repository<br/>@issues</div>', [
      '@documentation' => Link::fromTextAndUrl('Documentation', Url::fromUri('https://www.drupal.org/community-initiatives/starshot-demo-design-system'))->toString(),
      '@design_system' => Link::fromTextAndUrl('Design system (Figma)', Url::fromUri('https://www.figma.com/design/rh50nTZp6E5nG7M2VSZHnM/Drupal-XB-Content-Mockup%3A-Design-System-v1.7.0?node-id=15436-105142&t=nwmn0wcY73N85mfn-0'))->toString(),
      '@repository' => Link::fromTextAndUrl('Code repository (GitLab)', Url::fromUri('https://git.drupalcode.org/project/demo_design_system'))->toString(),
      '@issues' => Link::fromTextAndUrl('Report issues', Url::fromUri('https://www.drupal.org/project/issues/demo_design_system'))->toString(),
    ]);

    $form['civictheme_information'] = [
      '#type' => 'inline_template',
      '#template' => '{{ content|raw }}',
      '#context' => [
        'content' => $message,
      ],
      '#weight' => -100,
    ];
  }

}
