// phpcs:ignoreFile
import { knobRadios, knobBoolean } from '../../00-base/storybook/storybook.utils';
import CivicThemeStarshotPage from './old-starshot.stories.twig';

export default {
  title: 'Templates/Old Starshot',
  parameters: {
    layout: 'fullscreen',
  },
};

export const OldStarshot = (parentKnobs = {}) => {
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

  const props = {
    theme,
    show_hero: knobBoolean('Show hero', true, parentKnobs.show_hero, parentKnobs.knobTab),
    show_icons: knobBoolean('Show icons', true, parentKnobs.show_icons, parentKnobs.knobTab),
    show_case_study: knobBoolean('Show case study', true, parentKnobs.show_case_study, parentKnobs.knobTab),
    show_nav_cards: knobBoolean('Show nav cards', true, parentKnobs.show_nav_cards, parentKnobs.knobTab),
    show_resources: knobBoolean('Show resources', true, parentKnobs.show_resources, parentKnobs.knobTab),
  };

  return CivicThemeStarshotPage({
    ...props,
  });
};
