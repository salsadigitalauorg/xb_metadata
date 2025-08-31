// phpcs:ignoreFile
import { knobRadios, shouldRender, randomText } from '../../00-base/storybook/storybook.utils';
import CivicThemeStarshotHeading from './old-starshot-heading.twig';

export default {
  title: 'Atoms/Old Starshot Heading',
  parameters: {
    layout: 'fullscreen',
  },
};

export const OldStarshotHeading = (parentKnobs = {}) => {
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
    content: randomText(6),
    level: knobRadios(
      'Level',
      {
        1: '1',
        2: '2',
        3: '3',
        4: '4',
        5: '5',
        6: '6',
      },
      '2',
      parentKnobs.level,
      parentKnobs.knobTab,
    ),
    display: knobRadios(
      'Display',
      {
        None: '',
        'Drupal 2': 'drupal-2',
        'Drupal 3': 'drupal-3',
        'Drupal 4': 'drupal-4',
        'Drupal 5': 'drupal-5',
        'Drupal 6': 'drupal-6',
        'Drupal 7': 'drupal-7',
        'Drupal 8': 'drupal-8',
      },
      'drupal-2',
      parentKnobs.display,
      parentKnobs.knobTab,
    ),
    align: knobRadios(
      'Align',
      {
        Left: 'left',
        Center: 'center',
        Right: 'right',
      },
      'left',
      parentKnobs.align,
      parentKnobs.knobTab,
    ),
  };

  return shouldRender(parentKnobs) ? CivicThemeStarshotHeading({
    ...props,
  }) : props;
};
