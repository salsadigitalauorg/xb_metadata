// phpcs:ignoreFile
import { knobBoolean, shouldRender } from '../../00-base/storybook/storybook.utils';
import CivicThemeStarshotTwoColStory from './starshot-two-col.stories.twig';

export default {
  title: 'Base/Starshot Layout/Starshot Two Col',
  parameters: {
    layout: 'fullscreen',
  },
};

export const StarshotTwoCol = (parentKnobs = {}) => {
  const props = {
    show_column_one: knobBoolean('Show column one', true, parentKnobs.show_column_one, parentKnobs.knobTab),
    show_column_two: knobBoolean('Show column two', true, parentKnobs.show_column_two, parentKnobs.knobTab),
  };

  return shouldRender(parentKnobs) ? CivicThemeStarshotTwoColStory({
    ...props,
  }) : props;
};
