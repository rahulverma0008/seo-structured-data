# SEO Structured Data

Open Graph and Twitter Card meta tags for the home page, products, categories and manufacturers, so links shared on Facebook, Twitter/X, WhatsApp, LinkedIn etc. show a proper title, description and thumbnail.

**For OpenCart 4.0.0.0 - 4.1.0.4** (one package). For OpenCart 3.x use the `Opencart_V3.x` folder.

## How To Install
1) In your OpenCart admin panel go to Extensions > Installer
2) Upload `seo_structured_data.ocmod.zip` and click the green install button
3) Go to Extensions > Extensions, choose Modules from the extension type
4) Click the Install button of SEO Structured Data, then the edit button and configure it
5) Set the status to enabled and save

The module adds one event (catalog/view/common/header/after), it does not change any file of OpenCart.

## Features
1. Easy to install and setup
2. Works on all social platforms
3. Enhance link sharing experience
4. Supports Facebook App Id and Twitter username
5. Customizable thumbnail size
6. Works on all OpenCart 4 versions without OCMOD

## What is new in 2.0.0 (compared with the OpenCart 3 version)
* OpenCart 4.0.0.0 - 4.1.0.4 support, one header event instead of two.
* Values are HTML escaped: a quote, `&` or `<` in a description no longer breaks the meta tag (which could also inject markup into the page head).
* Home, category and manufacturer pages use `og:type = website` (only products are `product`).
* `twitter:image:width` / `height` are Twitter tags (name), not Open Graph properties.
* Uses the store URL of OpenCart 4 (`config_ssl` does not exist anymore) and language aware product, category and manufacturer URLs.
* Admin settings are validated (Twitter username, thumbnail size) and saved with the OpenCart 4 AJAX form.
* Settings keep their names, so an upgrade from the OpenCart 3 version keeps them.

## Testing
`_oc-test-harness/run.sh seo-structured-data/Opencart_V4.x --oc <version> --fresh` (from the parent folder) installs the zip in a real OpenCart and checks the install, the settings, the tags on every page type, escaping and the uninstall.
