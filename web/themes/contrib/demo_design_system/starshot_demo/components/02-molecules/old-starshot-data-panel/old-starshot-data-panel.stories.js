// phpcs:ignoreFile
import { knobBoolean, knobRadios, knobText, knobNumber, shouldRender, randomUrl, randomSentence } from '../../00-base/storybook/storybook.utils';
import CivicThemeOldStartshotDataPanel from './old-starshot-data-panel.twig';

export default {
  title: 'Molecules/Old Starshot Data Panel',
  parameters: {
    layout: 'centered',
    storyLayoutSize: 'large',
  },
};

export const OldStarshotDataPanel = (parentKnobs = {}) => {
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
    image: knobBoolean('Show image', true, parentKnobs.show_image, parentKnobs.knobTab) ? {
      url: 'assets/starshot/tesla_logo.png',
      alt: 'Data panel image alt text',
    } : null,
    description: knobText('Description', 'Tesla believes the faster the world stops relying on fossil fuels and moves towards a zero-emission future, the better.', parentKnobs.description, parentKnobs.knobTab),
    link: knobBoolean('Show link', true, parentKnobs.show_link, parentKnobs.knobTab) ? {
      text: knobText('Link text', 'Learn more', parentKnobs.link_text, parentKnobs.knobTab),
      url: knobText('Link URL', randomUrl(), parentKnobs.link_url, parentKnobs.knobTab),
    } : null,
  };

  // Adding dynamic number of Stats items.
  const panelsKnobTab = 'Stats';
  const numOfStats = knobNumber(
    'Number of stats',
    3,
    {
      range: true,
      min: 0,
      max: 10,
      step: 1,
    },
    parentKnobs.number_of_panels,
    panelsKnobTab,
  );

  const panels = [];
  let itr = 1;
  while (itr <= numOfStats) {
    panels.push({
      amount: knobText(`Stats ${itr} amount `, `+${itr}0`, parentKnobs[`panel_amount_${itr}`], panelsKnobTab),
      sub: knobText(`Stats ${itr} sub `, 'k', parentKnobs[`panel_sub_${itr}`], panelsKnobTab),
      description: `${knobText(`Stats ${itr} description`, randomSentence(5, 'stats-item'), parentKnobs[`panel_description_${itr}`], panelsKnobTab)}`,
    });
    itr += 1;
  }

  const combinedKnobs = { ...props, panels };

  return shouldRender(parentKnobs) ? CivicThemeOldStartshotDataPanel({
    ...combinedKnobs,
  }) : combinedKnobs;
};
