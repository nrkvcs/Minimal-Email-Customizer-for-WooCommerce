# Changelog

All notable changes to **Minimal Email Customizer for Woocommerce** are recorded here.
Versioning follows [Semantic Versioning](https://semver.org/). The current version lives in
`minimal-email-customizer-for-woocommerce/minimal-email-customizer-for-woocommerce.php` (plugin header and `WEC_VERSION`) and `readme.txt` (Stable tag).

## [1.3.1] - 2026-10-03
### Changed
- Plugin Check: renamed the public hooks `wec_shortcodes` → `mecw_shortcodes` and `wec_prepare_email` → `mecw_prepare_email` (hook names must carry the plugin prefix). Update any custom code that uses the old names.
- Preview and test-email AJAX handlers call `check_ajax_referer()` directly, so the nonce check is visible to static analysis.

## [1.3.0] - 2026-10-02
### Added
- **Import / Export** tab: export header/footer/design and/or email content to a JSON file and import it on another site. Imports are fully sanitized; email content is merged by email ID; optional copy of the logo into the media library.
- Update routine (`mecw_upgraded` action) that runs once after the plugin files change.
### Changed
- Code symbols renamed from `WEC_*` / `wec_boot` to `MECW_*` / `mecw_boot` so an older copy (folder `wc-email-customizer`) can never clash with this one. The older copy is deactivated automatically; settings (option `wec_settings`) are shared and kept.
- Uploading a newer zip now shows WordPress' "already installed, replace?" screen. This requires the folder and main file to keep the name `minimal-email-customizer-for-woocommerce`; do not rename them in future releases.

## [1.2.1] - 2026-10-02
### Fixed
- Link colour (Design → Accent / link colour) is now enforced with `!important` on body links, order-table links and footer links, so WooCommerce's default link styles can no longer override it. Buttons keep their own colours.

## [1.2.0] - 2026-10-02
### Added
- **Design tab**: body/heading fonts and custom font stack, text, heading and order-heading sizes, line height, heading weight/case/letter spacing, link underline, border colour, corner radius, spacing, heading style (bar or inline), button styling, Classic/Dark/Minimal presets and custom CSS.
- **Custom text only** body mode: replaces just the message between the heading and the order details; order table, addresses and the rest stay. On emails without an order table the text is shown above the default content.
- `[button url="" text=""]` shortcode that uses the button design settings.
- Body-mode selector now only shows the fields relevant to the chosen mode. "Fully custom body" is unchanged.
### Changed
- Prepared for the WordPress.org plugin directory: plugin folder, main file and text domain are now `minimal-email-customizer-for-woocommerce`; added `Requires Plugins: woocommerce`; name spelled "WooCommerce"; directory-format readme.txt.
- Layout and colour options moved from "Header & Footer" to the new Design tab. Existing saved values are kept.
- Numeric settings now accept 0 (e.g. corner radius).

## [1.1.0] - 2026-09-29
### Added
- Custom / third-party emails (e.g. from custom order status plugins) can be edited like built-in ones and are marked "custom / plugin email".
- Fallback that prints "additional content" for plugin templates that never output it.
- `wec_prepare_email` action so plugin emails can receive extra preview data.
### Changed
- Plugin renamed to "Minimal Email Customizer for Woocommerce"; author set to Norbert Kovacs.
- Email IDs are normalised for storage, so IDs with capitals or dots save correctly.
- Emails appended to the mailer late are now detected (second hook pass).

## [1.0.0] - 2026-09-29
### Added
- Initial release: fixed header and footer for all WooCommerce emails, per-email subject, heading, intro,
  additional content and fully custom body, 50+ order/customer/store shortcodes, live preview and test email.
