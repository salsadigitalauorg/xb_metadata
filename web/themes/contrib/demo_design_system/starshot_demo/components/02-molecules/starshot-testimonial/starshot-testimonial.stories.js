// phpcs:ignoreFile
import { knobBoolean, knobText, shouldRender } from '../../00-base/storybook/storybook.utils';
import CivicThemeStarshotTestimonial from './starshot-testimonial.twig';

export default {
  title: 'Molecules/Starshot Testimonial',
  parameters: {
    layout: 'fullscreen',
  },
};

export const StarshotTestimonial = (parentKnobs = {}) => {
  const props = {
    quote: knobText('Quote', 'For our 1,000+ websites, one of the benefits of Drupal is that accessibility compliance are already covered.', parentKnobs.quote, parentKnobs.knobTab),
    cite: knobText('Cite', 'Joyce Peralta, Manager, Digital Communications at McGill University', parentKnobs.cite, parentKnobs.knobTab),
    image: {
      url: knobText('Image url', './assets/starshot/testimonial_avatar.png', parentKnobs.image.src, parentKnobs.knobTab),
      alt: knobText('Image alt', 'Testimonial avatar', parentKnobs.image.alt, parentKnobs.knobTab),
    },
  };

  return shouldRender(parentKnobs) ? CivicThemeStarshotTestimonial({
    ...props,
  }) : props;
};
