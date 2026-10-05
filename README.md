# EZmails - Simple Email Customizer for WooCommerce

A lightweight, straightforward way to customize WooCommerce emails: one fixed header and footer, simple design options, editable email text and dynamic order shortcodes. No drag-and-drop builder, no Pro version.

- Fixed header (logo, tagline) and footer (rich text, social links) on every WooCommerce email
- Design tab: fonts, sizes, colours, layout, buttons, presets
- Per email: custom subject, heading, intro, additional content, "custom text only" mode (keeps the order details) or a fully custom body
- 50+ shortcodes such as `[order_number]`, `[customer_name]`, `[order_details]`, `[button]`
- Works with emails from other plugins (e.g. custom order status emails)
- Preview with a real order, send test emails
- Import / export settings between sites

## Install

Download the plugin folder `ezmails-simple-email-customizer-for-woocommerce` as a zip (or a release zip) and upload it under **Plugins → Add New → Upload Plugin**. Requires WordPress 5.8+, PHP 7.4+ and WooCommerce.

To build a zip from this repository:

```bash
zip -r ezmails-simple-email-customizer-for-woocommerce.zip ezmails-simple-email-customizer-for-woocommerce
```

Keep the folder name unchanged so WordPress offers to replace an installed copy when you upload a newer version.

## Development notes

- Plugin code lives in [`ezmails-simple-email-customizer-for-woocommerce/`](ezmails-simple-email-customizer-for-woocommerce)
- The WordPress.org readme is [`readme.txt`](ezmails-simple-email-customizer-for-woocommerce/readme.txt); version history is in [CHANGELOG.md](CHANGELOG.md)
- Hooks: `mecw_shortcodes` (filter, add shortcodes), `mecw_prepare_email` (action, preview data), `mecw_upgraded` (action after an update)

## License

GPL-2.0-or-later, see [LICENSE](LICENSE).

Author: [Norbert Kovacs](https://kovacs.cloud)
