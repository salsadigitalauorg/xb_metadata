// phpcs:ignoreFile
import { knobBoolean, knobRadios, knobText, shouldRender, randomUrl } from '../../00-base/storybook/storybook.utils';
import CivicThemeOldStarshotCaseStudy from './old-starshot-case-study.twig';

export default {
  title: 'Organisms/Old Starshot Case Study',
  parameters: {
    layout: 'centered',
    storyLayoutSize: 'large',
  },
};

export const OldStarshotCaseStudy = (parentKnobs = {}) => {
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
    featured_image: knobBoolean('Show image', true, parentKnobs.show_image, parentKnobs.knobTab) ? {
      url: 'assets/starshot/tesla_car.png',
      alt: 'Tesla car image alt text',
    } : null,
    title: knobText('Title', 'The Power of Drupal', parentKnobs.title, parentKnobs.knobTab),
    description: knobText('Description', 'Learn why Drupal powers one in 40 websites in the world including sites from Tesla, Pfizer and NBC.', parentKnobs.description, parentKnobs.knobTab),
    data_panel: knobBoolean('Show data panel', true, parentKnobs.data_panel, parentKnobs.knobTab) ? {
      image: {
        url: 'assets/starshot/tesla_logo.png',
        alt: 'Data panel image alt text',
      },
      description: 'Tesla believes the faster the world stops relying on fossil fuels and moves towards a zero-emission future, the better.',
      link: {
        text: 'Learn more',
        url: randomUrl(),
      },
      panels: [
        {
          amount: '+1',
          sub: 'k',
          description: 'Commits in the last week',
        },
        {
          amount: '+123',
          sub: 'k',
          description: 'Users actively contributing',
        },
        {
          amount: '+3',
          sub: 'k',
          description: 'Comments in the last week',
        },
      ],
    } : null,
  };

  return shouldRender(parentKnobs) ? CivicThemeOldStarshotCaseStudy({
    ...props,
  }) : props;
};
