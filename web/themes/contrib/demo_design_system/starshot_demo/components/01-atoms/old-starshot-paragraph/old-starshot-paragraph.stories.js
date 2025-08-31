// phpcs:ignoreFile
import { knobBoolean, knobText, knobRadios, shouldRender, randomText } from '../../00-base/storybook/storybook.utils';
import CivicThemeStarshotParagraph from './old-starshot-paragraph.twig';

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

  const allowHtml = knobBoolean('Allow html', false, parentKnobs.allow_html, parentKnobs.knobTab);

  const props = {
    theme,
    content: allowHtml ? `<strong>${randomText(2)}</strong> <em>${randomText(5)}</em>` : knobText('Content', randomText(6), parentKnobs.content, parentKnobs.knobTab),
    display: knobRadios(
      'Display',
      {
        Regular: 'regular',
        Large: 'large',
      },
      'regular',
      parentKnobs.display,
      parentKnobs.knobTab,
    ),
    color: knobRadios(
      'Color',
      {
        Body: 'body',
        'Body 2': 'body-2',
        'Body 3': 'body-3',
      },
      'body',
      parentKnobs.color,
      parentKnobs.knobTab,
    ),
    allow_html: allowHtml,
  };

  return shouldRender(parentKnobs) ? CivicThemeStarshotParagraph({
    ...props,
  }) : props;
};
