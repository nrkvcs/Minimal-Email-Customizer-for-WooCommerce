=== Minimal Email Customizer for WooCommerce ===
Contributors: norkovacs
Donate link: https://kovacs.cloud/donate
Tags: woocommerce, email, email customizer, email templates, shortcodes
Requires at least: 5.8
Tested up to: 7.1
Requires PHP: 7.4
Requires Plugins: woocommerce
Stable tag: 1.3.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Lightweight WooCommerce email customizer: fixed header and footer, simple design options, editable email text and dynamic order shortcodes.

== Description ==

I made this plugin to make it easier to customize WooCommerce emails. Most of the other options are too much: drag-and-drop builders and endless settings that are overkill for simple customization, and some of them only work properly once you buy the Pro version.

I originally built it for myself and my clients, to make my life easier. Then I decided to share it with the community. If you believe that sometimes less is more and you want a lightweight, straightforward option, this plugin is for you.

= What you get =

* **One fixed header and footer for every email.** Add your logo, a tagline, footer text and social links once and they appear on all WooCommerce emails, including emails from other plugins that use the standard WooCommerce email hooks.
* **Simple design controls.** Colours, fonts, font sizes, heading style, layout, buttons and ready-made Classic, Dark and Minimal presets. Need more? Add your own CSS.
* **Edit each email.** Change the subject, the heading, and the text of every email, in one of three ways:
    * keep the WooCommerce default body and add your own intro or extra text,
    * replace only the message between the heading and the order details, and keep the order table,
    * or replace the whole body with your own content.
* **Dynamic data with shortcodes.** Use shortcodes such as `[order_number]`, `[customer_name]`, `[order_total]`, `[billing_address]`, `[order_details]` and `[order_view_url]` in any field. There is also `[order_meta key="..."]` for custom order fields and `[button url="..." text="..."]` for styled buttons.
* **Import and export.** Save your settings and email content to a file and move them to another site in a few clicks.
* **Works with custom emails.** Emails added by other plugins, for example custom order status plugins, show up in the list and can be edited the same way.
* **Preview and test.** Preview any email with a real order from your store and send yourself a test email.
* **Lightweight.** One settings page under WooCommerce, no page builder, no external services, no tracking, no upsells.

= Shortcodes =

Shortcodes work in the subject, heading, intro, email text, additional content, custom body, header text and footer. A few examples:

* `[order_number]`, `[order_date]`, `[order_status]`, `[order_total]`, `[payment_method]`, `[shipping_method]`
* `[customer_name]`, `[customer_email]`, `[billing_first_name]`, `[billing_address]`, `[shipping_address]`
* `[order_details]`, `[customer_details]`, `[addresses]` (WooCommerce's own order table and address blocks)
* `[site_title]`, `[store_address]`, `[current_year]`, `[my_account_url]`
* `[order_view_url]`, `[order_pay_url]`, `[reset_password_url]`

The full list is on the Shortcodes tab of the settings page. Developers can add their own with the `mecw_shortcodes` filter.

= Notes =

* Plain-text emails only use the custom subject and additional content. The header, footer and design apply to HTML emails.
* Untick "Enable customizer" at any time to go back to the standard WooCommerce emails instantly.

== Installation ==

1. Make sure WooCommerce is installed and active.
2. Upload the plugin to the `/wp-content/plugins/` directory, or install it from the Plugins screen in WordPress.
3. Activate the plugin through the Plugins screen.
4. Go to **WooCommerce → Email Customizer** to set up your header, footer and design, then edit your emails.
5. Use the **Preview & Test** tab to check the result.

== Frequently Asked Questions ==

= Does it require WooCommerce? =

Yes. The plugin customizes WooCommerce emails and does nothing without WooCommerce.

= Where are the settings? =

Under **WooCommerce → Email Customizer**.

= Can I change only the text of an email and keep the order details? =

Yes. Set the email's **Body mode** to "Custom text only". Your text replaces the message between the heading and the order details, and the order table and addresses stay.

= Can I fully replace an email's content? =

Yes. Choose "Fully custom body" and write your own content. Use `[order_details]` if you want to include the order table.

= Does it work with emails from other plugins? =

Emails registered the standard WooCommerce way, such as emails from custom order status plugins, are listed and can be edited. Emails that ship with fully custom markup that bypasses the WooCommerce email header and footer may not receive the new header and footer.

= Do custom fonts work? =

Most email apps ignore web fonts, so the plugin offers email-safe font stacks. You can enter your own font stack, but always end it with a safe fallback such as Arial.

= How do I go back to the default WooCommerce emails? =

Untick "Enable customizer" on the Header & Footer tab. Your settings are kept.

= How do I move my settings to another site? =

Go to **WooCommerce → Email Customizer → Import / Export**. Export a file on the first site, then import it on the second one. You can choose to transfer the header, footer and design, the email content, or both. Emails that do not exist on the second site (for example from a plugin that is not installed there) are kept in the file and used as soon as that plugin is active.

= How do I update the plugin from a zip file? =

Upload the new zip on **Plugins → Add New → Upload Plugin**. WordPress will tell you that the plugin is already installed and offer to replace the current version with the uploaded one. Your settings are kept.

= Does it work with High-Performance Order Storage (HPOS)? =

Yes, it is compatible with HPOS.

= Can I add my own shortcodes? =

Yes, with the `mecw_shortcodes` filter. Each shortcode has a group, a label, a callback and an HTML flag.

= Does the plugin collect data or connect to external services? =

No. It stores its settings in your database and makes no external requests. Settings are removed when you delete the plugin.

== Screenshots ==

1. Header & Footer: set up the fixed logo, tagline, footer text and social links for all emails.
2. Design: typography, colours, layout, buttons and presets.
3. Email Content: edit the subject, heading and text of every email.
4. Shortcodes: insert dynamic order and customer data with one click.
5. Preview & Test: preview any email with a real order and send a test email.

== Changelog ==

= 1.3.1 =
* Plugin Check fixes: developer hooks are now prefixed (`mecw_shortcodes`, `mecw_prepare_email`) and the preview and test-email requests verify their security nonce directly.

= 1.3.0 =
* New: Import / Export tab to back up and migrate settings and email content between sites.
* Update-friendly: uploading a newer zip now offers to replace the installed version, and a leftover copy from the old folder name is deactivated automatically.
* Settings are kept on update; a version check runs once after each update.

= 1.2.1 =
* Fix: the link colour setting is now always applied to links in the email body, even where WooCommerce's own styles tried to override it.

= 1.2.0 =
* New Design tab: typography (fonts, sizes, weight, case, spacing), colours, layout, buttons, presets and custom CSS.
* New "Custom text only" body mode: replace just the text between the heading and the order details.
* New `[button]` shortcode.
* Body mode settings now show only the fields that apply to the selected mode.

= 1.1.0 =
* Support for custom and third-party emails, for example custom order status emails.
* Email IDs with capitals or dots are now saved correctly.

= 1.0.0 =
* Initial release.

== Upgrade Notice ==

= 1.3.1 =
Developer hooks were renamed to `mecw_shortcodes` and `mecw_prepare_email`. Only relevant if you extended the plugin.

= 1.3.0 =
Adds Import / Export and safer updates. Settings are kept.

= 1.2.1 =
Fixes the link colour not being applied in emails.

= 1.2.0 =
Adds a Design tab and a "Custom text only" body mode. Existing settings are kept.
