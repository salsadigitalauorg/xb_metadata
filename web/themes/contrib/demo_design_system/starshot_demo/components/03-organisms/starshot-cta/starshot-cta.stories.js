// phpcs:ignoreFile
import { knobText, shouldRender } from '../../00-base/storybook/storybook.utils';
import CivicThemeStarshotCTA from './starshot-cta.twig';

export default {
  title: 'Organisms/Starshot CTA',
  parameters: {
    layout: 'fullscreen',
    knobs: {
      escapeHTML: false,
    },
  },
};

export const StarshotCTA = (parentKnobs = {}) => {
  const props = {
    title: knobText('Title', 'Start your <em>Drupal</em> journey', parentKnobs.title, parentKnobs.knobTab),
    content_right: knobText('Content Right', '<p>Get your website up and running faster.<br> <strong>Drupal CMS</strong> gives you the tools to bring your digital ambitions to life without restricting your creativity.</p>', parentKnobs.content_right, parentKnobs.knobTab),
    content_right_cta_button: {
      text: knobText('Content Right Cta Button Text', 'Get started', parentKnobs.content_right_cta_button_text, parentKnobs.knobTab),
      url: knobText('Content Right Cta Button Url', '/', parentKnobs.content_right_cta_button_url, parentKnobs.knobTab),
    },
    content_right_cta_button2: {
      text: knobText('Content Right Cta Button 2 Text', 'Learn More about Drupal CMS', parentKnobs.content_right_cta_button2_text, parentKnobs.knobTab),
      url: knobText('Content Right Cta Button 2 Url', '/', parentKnobs.content_right_cta_button2_url, parentKnobs.knobTab),
    },
    content_right_bottom: knobText('Content Right Bottom', '<p>Drupal Core provides the base features you rely on and allows you to start building from scratch.</p>', parentKnobs.content_right_bottom, parentKnobs.knobTab),
    content_right_bottom_button: {
      text: knobText('Content Right Bottom Button Text', 'Download Drupal Core', parentKnobs.content_right_bottom_button_text, parentKnobs.knobTab),
      url: knobText('Content Right Bottom Button Url', '/', parentKnobs.content_right_bottom_button_url, parentKnobs.knobTab),
    },
  };

  return shouldRender(parentKnobs) ? CivicThemeStarshotCTA({
    ...props,
  }) : props;
};
