// phpcs:ignoreFile
import { knobText, knobBoolean, knobRadios, shouldRender, slotKnobs, randomText } from '../../00-base/storybook/storybook.utils';
import CivicThemeOldStarshotContainer from './old-starshot-container.twig';

export default {
  title: 'Base/Starshot Layout/Old Starshot Container',
  parameters: {
    layout: 'fullscreen',
  },
};

export const OldStarshotContainer = (parentKnobs = {}) => {
  const props = {
    content: knobText('Content', randomText(10), parentKnobs.content, parentKnobs.knobTab),
    background_url: knobText('Background URL', 'assets/starshot/starshot_background_1.png', parentKnobs.background_url, parentKnobs.knobTab),
    constrain: knobBoolean('Constrain', true, parentKnobs.constrain, parentKnobs.knobTab),
    vertical_spacing: knobRadios(
      'Vertical spacing',
      {
        None: 'none',
        Top: 'top',
        Bottom: 'bottom',
        Both: 'both',
      },
      'none',
      parentKnobs.vertical_spacing,
      parentKnobs.knobTab,
    ),
    vertical_spacing_inner: knobRadios(
      'Vertical spacing inner',
      {
        None: 'none',
        Top: 'top',
        Bottom: 'bottom',
        Both: 'both',
      },
      'both',
      parentKnobs.vertical_spacing_inner,
      parentKnobs.knobTab,
    ),
  };

  return shouldRender(parentKnobs) ? CivicThemeOldStarshotContainer({
    ...props,
    ...slotKnobs([
      'content',
    ]),
  }) : props;
};
