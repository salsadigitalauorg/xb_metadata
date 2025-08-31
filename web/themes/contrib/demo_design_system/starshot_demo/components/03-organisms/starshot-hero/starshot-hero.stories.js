// phpcs:ignoreFile
import { knobText, shouldRender } from '../../00-base/storybook/storybook.utils';
import CivicThemeStarshotHero from './starshot-hero.twig';

export default {
  title: 'Organisms/Starshot Hero',
  parameters: {
    layout: 'fullscreen',
  },
};

export const StarshotHero = (parentKnobs = {}) => {
  const props = {
    title: knobText('Title', 'Create ambitious experiences that scale', parentKnobs.title, parentKnobs.knobTab),
    summary: knobText('Summary', 'Drupal is a fully composable CMS that allows you to design a digital experience around your vision.', parentKnobs.summary, parentKnobs.knobTab),
    link: {
      text: knobText('Link text', 'Learn more', parentKnobs.link_text, parentKnobs.knobTab),
      url: knobText('Link URL', '/', parentKnobs.link_url, parentKnobs.knobTab),
    },
    image: {
      url: knobText('Image URL', './assets/starshot/starshot_5.png', parentKnobs.image_url, parentKnobs.knobTab),
      alt: knobText('Image alt', '', parentKnobs.image_alt, parentKnobs.knobTab),
    },
  };

  return shouldRender(parentKnobs) ? CivicThemeStarshotHero({
    ...props,
  }) : props;
};
