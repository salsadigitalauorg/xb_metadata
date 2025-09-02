# CivicTheme Design System Metadata Integration with Experience Builder

<img src="https://github.com/user-attachments/assets/c7c3283b-2580-4434-8cce-771cb02aa1f7" width="300" align="right" />

This project is a **proof of concept** demonstrating the customization of Drupal's Experience Builder module and its xb_ai submodule for CivicTheme's design system metadata integration.

## Project Overview

This repository showcases how Experience Builder can be extended to integrate with CivicTheme's design system metadata, providing enhanced AI-powered page building capabilities. The project includes:

- Customized Experience Builder module integration
- xb_ai submodule enhancements for design system metadata
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

# Install recipes
ddev drush recipe /var/www/html/recipes/xb_demo
ddev drush recipe /var/www/html/recipes/xb_page
ddev drush recipe /var/www/html/recipes/civictheme_xb_demo
ddev drush cr

# Enable AI modules
ddev drush en xb_ai ai_agents

# For Media Agent
First setup the OpenAI provider in this case - see below.
ddev drush recipe /var/www/html/recipes/xb_media_search

# Now log in
ddev drush user:login xb/xb_page/2/editor
```
Now open the link Drush generated at the end to go right into Experience Builder.

## AI Provider Setup 🤖

To use the AI-powered features, you'll need to setup an AI provider. Install and configure one of the following modules:

- **OpenAI Provider**: [ai_provider_openai](https://www.drupal.org/project/ai_provider_openai)
- **Amazee.io Provider**: [ai_provider_amazeeio](https://www.drupal.org/project/ai_provider_amazeeio)

Follow the documentation for your chosen provider to configure API keys and settings.
