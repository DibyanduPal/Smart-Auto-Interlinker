=== Smart Auto Interlinker ===
Contributors: smart-auto-interlinker
Tags: internal links, seo, interlinking, keywords
Requires at least: 5.8
Tested up to: 6.6
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Create per-post keyword → URL mappings and automatically interlink occurrences site-wide.

== Description ==

Smart Auto Interlinker lets you define keyword → URL mappings on any post or page. The plugin stores mappings as post meta and uses a cached index to insert links dynamically when posts are viewed.

== Installation ==

1. Upload the `smart-auto-interlinker` folder to the `/wp-content/plugins/` directory.
2. Activate the plugin through the "Plugins" menu in WordPress.
3. Visit "Settings" → "Smart Auto Interlinker" to configure data removal on uninstall.
4. Edit any post or page and add mappings in the meta box.

== Frequently Asked Questions ==

= How do I add mappings? =

Edit a post or page and enter one mapping per line in the format `keyword | URL` in the meta box.

= How can I remove all plugin data on uninstall? =

Enable the "Delete data on uninstall" checkbox in the plugin settings. When you uninstall the plugin, it will remove saved options and all keyword mappings.

= Does it work on pages as well as posts? =

Yes. The meta box appears on both posts and pages.

== Changelog ==

= 1.0.0 =
* Initial release.
