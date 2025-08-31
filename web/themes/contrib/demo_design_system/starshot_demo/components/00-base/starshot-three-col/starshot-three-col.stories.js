// phpcs:ignoreFile
import { knobBoolean, shouldRender } from '../../00-base/storybook/storybook.utils';
import CivicThemeStarshotThreeColStory from './starshot-three-col.stories.twig';

export default {
  title: 'Base/Starshot Layout/Starshot Three Col',
  parameters: {
    layout: 'fullscreen',
  },
};

export const StarshotThreeCol = (parentKnobs = {}) => {
  const props = {
    show_column_one: knobBoolean('Show column one', true, parentKnobs.show_column_one, parentKnobs.knobTab),
    show_column_two: knobBoolean('Show column two', true, parentKnobs.show_column_two, parentKnobs.knobTab),
    show_column_three: knobBoolean('Show column three', true, parentKnobs.show_column_three, parentKnobs.knobTab),
  };

  return shouldRender(parentKnobs) ? CivicThemeStarshotThreeColStory({
    ...props,
  }) : props;
};
