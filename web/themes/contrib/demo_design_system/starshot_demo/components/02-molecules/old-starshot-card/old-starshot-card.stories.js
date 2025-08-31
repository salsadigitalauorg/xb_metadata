// phpcs:ignoreFile
import { knobBoolean, knobRadios, knobSelect, knobText, shouldRender, randomUrl } from '../../00-base/storybook/storybook.utils';
import CivicThemeStarshotCard from './old-starshot-card.twig';

export default {
  title: 'Molecules/Old Starshot Card',
  parameters: {
    layout: 'centered',
    storyLayoutSize: 'large',
  },
};

export const OldStarshotCard = (parentKnobs = {}) => {
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
    display: knobSelect('Display',
      {
        Vertical: 'vertical',
        Horizontal: 'horizontal',
        Centered: 'centered',
        Overlay: 'overlay',
      },
      'vertical',
      parentKnobs.display,
      parentKnobs.knobTab),
    background: knobSelect('Background',
      {
        None: '',
        Background: 'background',
        'Background 2': 'background-2',
        'Background 3': 'background-3',
        'Background 4': 'background-4',
        'Background 5': 'background-5',
        'Background 6': 'background-6',
      },
      'background-2',
      parentKnobs.background,
      parentKnobs.knobTab),
    image: knobBoolean('Show image', false, parentKnobs.show_image, parentKnobs.knobTab) ? {
      url: 'assets/starshot/starshot_4.png',
      alt: 'Card image alt text',
    } : null,
    tags: knobBoolean('Show tags', false, parentKnobs.show_tags, parentKnobs.knobTab) ? [
      { icon: 'education-book', content: 'Blogs' },
    ] : null,
    title: knobText('Title', 'Card title', parentKnobs.title, parentKnobs.knobTab),
    summary: knobText('Summary', 'Lorem ipsum eiusmod dolore consectetur pariatur ut esse sunt sit laboris labore ea aliquip id esse.', parentKnobs.summary, parentKnobs.knobTab),
    link: knobBoolean('Show link', false, parentKnobs.show_link, parentKnobs.knobTab) ? {
      text: knobText('Link text', 'Link text', parentKnobs.link_text, parentKnobs.knobTab),
      url: knobText('Link URL', randomUrl(), parentKnobs.link_url, parentKnobs.knobTab),
      is_external: knobBoolean('Link is external', false, parentKnobs.link_is_external, parentKnobs.knobTab),
      is_new_window: knobBoolean('Open in a new window', false, parentKnobs.link_is_new_window, parentKnobs.knobTab),
    } : null,
  };

  return shouldRender(parentKnobs) ? CivicThemeStarshotCard({
    ...props,
  }) : props;
};
