=== Site Vigil ===
Contributors: sitevigil
Tags: monitoring, uptime, analytics
Requires at least: 5.8
Tested up to: 6.4
Requires PHP: 7.4
Stable tag: 0.3.0
License: GPLv2 or later

Lightweight visitor tracking and uptime monitoring for WordPress, powered by Site Vigil.

== Description ==

Site Vigil monitors your site's uptime, SSL/domain expiry, and visitor traffic
from Cloudflare's edge — this plugin never checks the site itself, it only
injects a small visitor-tracking snippet and, once connected, shows a
read-only summary of what Site Vigil already knows about your site.

**Features:**
* Privacy-friendly visitor tracking snippet
* Connect to your Site Vigil dashboard (automatic or via pairing code)
* At-a-glance status widget: uptime, last incident, SSL/domain expiry, traffic

== Installation ==

1. Upload the plugin to `/wp-content/plugins/site-vigil`
2. Activate the plugin
3. Go to Settings → Site Vigil to set your tracking ID and connect the plugin
   to your Site Vigil account

== Changelog ==

= 0.3.0 =
* Self-hosted auto-updates via GitHub Releases
* Added GPLv2 license, security policy, and clean-uninstall support

= 0.2.0 =
* Added the connect handshake (automatic + pairing code) and the read-only
  site-summary widget

= 0.1.0 =
* Initial release: tracking snippet + tracking ID setting
