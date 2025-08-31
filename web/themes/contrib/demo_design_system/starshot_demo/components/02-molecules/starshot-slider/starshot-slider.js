const hasSplide = document.querySelector('.splide');

if (hasSplide) {
  // Splide does not clear out existing pagination if called twice.
  document.querySelectorAll('.splide__pagination').forEach(paginationEl => {
    paginationEl.innerHTML = '';
  });
  // eslint-disable-next-line no-undef
  const splide = new Splide('.splide', {
    type: 'loop',
    perPage: 5,
    gap: '1rem',
    pagination: true,
    focus: 'center',
    breakpoints: {
      768: {
        perPage: 1,
      },
      1440: {
        perPage: 3,
      },
      1920: {
        perPage: 3,
      },
      2560: {
        perPage: 4,
      },
      3840: {
        perPage: 5,
      },
    },
  });

  splide.mount();
}
