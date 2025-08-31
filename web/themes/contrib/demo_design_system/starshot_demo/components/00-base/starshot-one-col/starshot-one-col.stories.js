// phpcs:ignoreFile
import { knobBoolean, shouldRender } from '../../00-base/storybook/storybook.utils';
import CivicThemeStarshotOneColStory from './starshot-one-col.stories.twig';

export default {
  title: 'Base/Starshot Layout/Starshot One Col',
  parameters: {
    layout: 'fullscreen',
  },
};

export const StarshotOneCol = (parentKnobs = {}) => {
  const props = {
    show_column_one: knobBoolean('With column one', true, parentKnobs.show_column_one, parentKnobs.knobTab),
  };

  return shouldRender(parentKnobs) ? CivicThemeStarshotOneColStory({
    ...props,
  }) : props;
};
