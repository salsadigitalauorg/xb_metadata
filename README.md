# CivicTheme Design System Metadata Integration with Experience Builder

<img src="https://github.com/user-attachments/assets/c7c3283b-2580-4434-8cce-771cb02aa1f7" width="300" align="right" />

This project is a **proof of concept** demonstrating the customization of Drupal's Experience Builder module and its canvas_ai submodule for CivicTheme's design system metadata integration.

## Project Overview

This repository showcases how Experience Builder can be extended to integrate with CivicTheme's design system metadata, providing enhanced AI-powered page building capabilities. The project includes:

- Customized Experience Builder module integration
- canvas_ai submodule enhancements for design system metadata
- CivicTheme design system integration
- Metadata-driven component suggestions and layouts

**Note:** This is a development proof of concept built on Experience Builder, which is [currently under heavy development on drupal.org](https://www.drupal.org/project/experience_builder). Experience Builder is not yet stable and could change at any time without warning.

## Getting Started 🚀

This project requires cloning since it includes customized Experience Builder modules in `web/modules/custom/`. We use [DDEV](https://ddev.com/get-started/) (version 1.24.0 or later) as the default development tool for easy setup.

Clone this repository and run the following commands to get started:
```shell
git clone git@github.com:salsadigitalauorg/xb_metadata.git
cd xb_metadata
ddev start
ddev composer install
ddev drush si -y

# Install Drupal recipes
ddev drush recipe /var/www/html/recipes/canvas_demp
ddev drush recipe /var/www/html/recipes/civictheme_canvas_demo
ddev drush cr

# For Media Agent
# Ensure the OpenAI provider is configured (see below)
ddev drush recipe /var/www/html/recipes/canvas_media_search
ddev drush cr

# Now log in
ddev drush user:login canvas/editor/canvas_page/1
```
Now open the link Drush generated at the end to go right into Experience Builder.

## AI Provider Setup 🤖

This project ships with the OpenAI provider preconfigured via the `civictheme_canvas_demo` recipe. It uses the Drupal Key module to read the API key from a DDEV environment variable.

What’s included:
- A Key entity with ID `openai` that uses the Key module’s Environment provider to read `OPENAI_API_KEY`.
- `ai_provider_openai.settings` points `api_key` to that Key (`openai`).
- DDEV is configured to expose `OPENAI_API_KEY` into the web container.

Setup instructions:
1) Copy the example env file and set your key
```bash
cp .ddev/.env.example .ddev/.env
echo "OPENAI_API_KEY=sk-your-real-key" >> .ddev/.env
```

2) Restart DDEV to load the variable
```bash
ddev restart
```

Notes and references:
- DDEV customization & environment variables: https://docs.ddev.com/en/stable/users/extend/customization-extendibility/
- Securing environment variables with Drupal Key module: https://blog.horizontaldigital.com/securing-environment-variables-drupal-key-module
