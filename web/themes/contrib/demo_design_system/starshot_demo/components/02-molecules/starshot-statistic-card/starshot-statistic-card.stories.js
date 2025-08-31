// phpcs:ignoreFile
import { knobBoolean, knobRadios, knobText, shouldRender, slotKnobs } from '../../00-base/storybook/storybook.utils';
import CivicThemeStarshotStatisticCard from './starshot-statistic-card.twig';

export default {
  title: 'Organisms/Starshot Statistic Card',
  parameters: {
    layout: 'fullscreen',
  },
};

export const StarshotStatisticCard = (parentKnobs = {}) => {

  // @fixme use stats card.
  const props = {
    theme: knobRadios(
      'Theme',
      {
        Light: 'light',
        Dark: 'dark',
      },
      'light',
      parentKnobs.theme,
      parentKnobs.knobTab,
    ),
    number: knobText('Number', '70', parentKnobs.number, parentKnobs.knobTab),
    suffix: knobText('Suffix', 'k', parentKnobs.suffix, parentKnobs.knobTab),
    description: knobText('Description', 'Lorem ipsum', parentKnobs.description, parentKnobs.knobTab),
    align: knobText('Align', 'left', parentKnobs.align, parentKnobs.knobTab),
  };

  return shouldRender(parentKnobs) ? CivicThemeStarshotStatisticCard({
    ...props,
  }) : props;
};
