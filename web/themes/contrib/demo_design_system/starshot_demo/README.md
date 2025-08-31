# Starshot Demo Drupal theme

Based on [CivicTheme](https://www.drupal.org/project/civictheme) Drupal theme.

## Compiling front-end assets.

    npm install
    npm run build

## Adding default content

   ddev drush recipe themes/contrib/demo_design_system/starshot_demo/recipes/starshot_demo_content/

## Linting code

    npm run lint

    npm run lint-fix

## Starting a local Storybook instance

    npm run storybook

## Updating sub-theme

See [Version update](https://docs.civictheme.io/drupal-theme/version-update)

## Maintenance

### Updating minor dependencies

```bash
npm install -g npm-check-updates
npx npm-check-updates -u --target minor
```

---

For additional information, please refer to
the [Documentation site](https://docs.civictheme.io/drupal-theme)
