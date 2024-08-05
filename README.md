# Joomla Seo AutoFill
A Joomla 5.x plugin that automatically generates website meta descriptions, Open Graph meta tags, Twitter Card tags, and canonical URLs.

## Installation

1. **Download the Plugin:**
   - Visit the [GitHub Releases page](https://github.com/zeroalphagit/Joomla-Seo-AutoFill/releases) to download the latest version of the plugin as a ZIP file.

2. **Install the Plugin:**
   - Log in to your Joomla administrator backend.
   - Navigate to `Extensions` > `Manage` > `Install`.
   - Under the `Upload Package File` tab, click `Choose File` and select the downloaded ZIP file.
   - Click `Upload & Install` to upload and install the plugin.

3. **Enable the Plugin:**
   - After installation, go to `Extensions` > `Plugins`.
   - Search for "SEO AutoFill" in the list of plugins.
   - Click on the plugin name to open the plugin settings.
   - Change the status to `Enabled` and click `Save & Close`.

4. **Configure the Plugin:**
   - The plugin will automatically use content from your articles to generate meta tags. There are no additional configuration settings required.

## Usage

This plugin adds the following metadata to your pages:

* `<meta name="description" content="Automatically generated meta description from your article content">`
* Open Graph meta tags:
  * `<meta property="og:title" content="Automatically generated title from your article">`
  * `<meta property="og:type" content="website">`
  * `<meta property="og:url" content="Current page URL">`
  * `<meta property="og:image" content="First image found in article content">`
  * `<meta property="og:description" content="Automatically generated Open Graph description from your article content">`
* Twitter Card tags:
  * `<meta name="twitter:card" content="summary_large_image">`
  * `<meta name="twitter:title" content="Automatically generated title from your article">`
  * `<meta name="twitter:description" content="Automatically generated Twitter Card description from your article content">`
  * `<meta name="twitter:image" content="First image found in article content">`
* Canonical URL:
  * `<link rel="canonical" href="Current page URL">`

## Requirements

* Joomla 5.x or later.

## Support

* Please visit the [issues page](https://github.com/zeroalphagit/Joomla-Seo-AutoFill/issues) for this project.
