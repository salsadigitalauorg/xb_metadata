// phpcs:ignoreFile
import { knobBoolean, shouldRender } from '../../00-base/storybook/storybook.utils';
import CivicThemeStarshotFourColStory from './starshot-four-col.stories.twig';

export default {
  title: 'Base/Starshot Layout/Starshot Four Col',
  parameters: {
    layout: 'fullscreen',
  },
};

export const StarshotFourCol = (parentKnobs = {}) => {
  const props = {
    show_column_one: knobBoolean('Show column one', true, parentKnobs.show_column_one, parentKnobs.knobTab),
    show_column_two: knobBoolean('Show column two', true, parentKnobs.show_column_two, parentKnobs.knobTab),
    show_column_three: knobBoolean('Show column three', true, parentKnobs.show_column_three, parentKnobs.knobTab),
    show_column_four: knobBoolean('Show column four', true, parentKnobs.show_column_four, parentKnobs.knobTab),
  };

  return shouldRender(parentKnobs) ? CivicThemeStarshotFourColStory({
    ...props,
  }) : props;
};
