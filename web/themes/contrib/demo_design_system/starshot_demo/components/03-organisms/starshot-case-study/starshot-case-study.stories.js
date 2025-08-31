// phpcs:ignoreFile
import { knobBoolean, knobText, shouldRender } from '../../00-base/storybook/storybook.utils';
import CivicThemeStarshotCaseStudy from './starshot-case-study.twig';

export default {
  title: 'Organisms/Starshot Case Study',
  parameters: {
    layout: 'fullscreen',
  },
};

export const StarshotCaseStudy = (parentKnobs = {}) => {
  const props = {
    title: knobText('Title', 'Learn why 1 in 8 enterprise websites runs on Drupal', parentKnobs.title, parentKnobs.knobTab),
    summary: knobText('Summary', 'Nestlé leveraged Drupal to simplify, standardize, and centralize its global digital properties.', parentKnobs.summary, parentKnobs.knobTab),
    stats: knobBoolean('Has stats', true, parentKnobs.stats, parentKnobs.knobTab) ? [
      {
        top: '60%',
        bottom: 'of sites built on shared codebase',
      },
      {
        top: '90%',
        bottom: 'of sites built with Drupal',
      },
    ] : null,
    link: {
      text: knobText('Link text', 'Learn more', parentKnobs.link_text, parentKnobs.knobTab),
      url: knobText('Link URL', '/', parentKnobs.link_url, parentKnobs.knobTab),
    },
    logo: {
      url: knobText('Logo URL', './assets/starshot/starshot_7.png', parentKnobs.logo_url, parentKnobs.knobTab),
      alt: knobText('Logo alt', '', parentKnobs.logo_alt, parentKnobs.knobTab),
    },
    image: {
      url: knobText('Image URL', './assets/starshot/starshot_6.png', parentKnobs.image_url, parentKnobs.knobTab),
      alt: knobText('Image alt', '', parentKnobs.image_alt, parentKnobs.knobTab),
    },
  };

  return shouldRender(parentKnobs) ? CivicThemeStarshotCaseStudy({
    ...props,
  }) : props;
};
