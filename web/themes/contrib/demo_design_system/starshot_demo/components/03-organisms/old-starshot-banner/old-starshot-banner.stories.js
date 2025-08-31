// phpcs:ignoreFile
import { knobBoolean, knobRadios, knobText, shouldRender, slotKnobs } from '../../00-base/storybook/storybook.utils';
import CivicThemeStarshotBanner from './old-starshot-banner.twig';
import CivicThemeStarshotParagraph from '../../01-atoms/old-starshot-paragraph/old-starshot-paragraph.twig';

export default {
  title: 'Organisms/Old Starshot Banner',
  parameters: {
    layout: 'fullscreen',
  },
};

export const OldStarshotBanner = (parentKnobs = {}) => {
  const theme = knobRadios(
    'Theme',
    {
      Light: 'light',
      Dark: 'dark',
    },
    'light',
    parentKnobs.theme,
    parentKnobs.knobTab,
  );

  const title = knobText('Title', 'Build the Open Web with Drupal', parentKnobs.title, parentKnobs.knobTab);

  const knobs = {
    theme,
    title,
    show_featured_image: knobBoolean('Show featured image', true, parentKnobs.show_featured_image, parentKnobs.knobTab),
    show_content_text: knobBoolean('Show content text', true, parentKnobs.show_content_text, parentKnobs.knobTab),
  };

  const props = {
    title: knobs.title,
    theme,
    modifier_class: knobs.modifier_class,
  };

  if (knobs.show_featured_image) {
    props.featured_image = {
      url: './assets/backgrounds/starshot_demo_banner.png',
      alt: 'Featured image alt text',
    };
  }

  if (knobs.show_content_text) {
    let content = '';

    content += CivicThemeStarshotParagraph({
      theme,
      allow_html: true,
      size: 'extra-large',
      content: `<p class="ct-body-large-drupal">Drupal is the open-source CMS that helps you deliver ambitious, elegant, and performant digital experiences at scale.</p>`,
    });

    props.content = content;
  }

  return shouldRender(parentKnobs) ? CivicThemeStarshotBanner({
    ...props,
    ...slotKnobs([
      'content',
    ]),
  }) : props;
};
