// phpcs:ignoreFile
import { knobBoolean, knobText, knobRadios, shouldRender, randomText } from '../../00-base/storybook/storybook.utils';
import CivicThemeStarshotHeaderPanel from './starshot-header-panel.twig';

export default {
  title: 'Atoms/Old Starshot Paragraph',
  parameters: {
    layout: 'fullscreen',
  },
};

export const OldStarshotParagraph = (parentKnobs = {}) => {
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
    column_one: 'One column',
    column_two: 'Two column',
  };

  return shouldRender(parentKnobs) ? CivicThemeStarshotHeaderPanel({
    ...props,
  }) : props;
};
