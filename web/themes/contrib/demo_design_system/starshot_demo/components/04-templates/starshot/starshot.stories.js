// phpcs:ignoreFile
import { knobBoolean } from '../../00-base/storybook/storybook.utils';
import CivicThemeStarshotPage from './starshot.stories.twig';

export default {
  title: 'Templates/Starshot',
  parameters: {
    layout: 'fullscreen',
  },
};

export const Starshot = (parentKnobs = {}) => {
  const show = true;
  const props = {
    show_hero: knobBoolean('Show hero', show, parentKnobs.show_hero, parentKnobs.knobTab),
    show_listing_testimonial: knobBoolean('Show listing testimonial', show, parentKnobs.show_listing_testimonial, parentKnobs.knobTab),
    show_case_study: knobBoolean('Show case study', show, parentKnobs.show_case_study, parentKnobs.knobTab),
    show_cta: knobBoolean('Show cta', show, parentKnobs.show_cta, parentKnobs.knobTab),
    show_feature_slide: knobBoolean('Show feature slide', show, parentKnobs.show_feature_slide, parentKnobs.knobTab),
    show_stats: knobBoolean('Show stats', show, parentKnobs.show_stats, parentKnobs.knobTab),
  };

  return CivicThemeStarshotPage({
    ...props,
  });
};
