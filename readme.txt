=== Site Vigil ===
Contributors: sitevigil
Tags: monitoring, uptime, analytics
Requires at least: 5.8
Tested up to: 6.4
Requires PHP: 7.4
Stable tag: 0.4.3
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

= 0.4.3 =
* Widened the connector card (620px → 820px max-width) — it read too narrow
  on a standard desktop wp-admin screen
* Added responsive breakpoints: the stat grid drops from 3 to 2 to 1 columns
  on narrower screens instead of staying sparse or overflowing

= 0.4.2 =
* Removed the Tracking ID field from "Connect manually" — a pairing code
  always sets it automatically, so a manually typed value was just getting
  silently overwritten. Only the pairing code is entered by hand now.

= 0.4.1 =
* Restored the "Avg response" stat tile (6 tiles total, matching the mockup)
  now that get-site-summary actually computes and returns avg_response_ms

= 0.4.0 =
* Settings screen rebuilt to match the shared connector-contract mockup:
  brand header, pulse-dot status, incident line, and a down-state alert banner
* "Connect manually" is now one section with both Tracking ID and Pairing
  code fields behind a single Save button, replacing the separate top-of-page
  Tracking ID form
* Tracking ID is now populated automatically by the connect handshake
  (bundled with the plugin token) instead of always requiring manual entry

= 0.3.1 =
* Redesigned the Connector settings screen: status badge, at-a-glance stat
  tiles, and a collapsed pairing-code field instead of a plain table
* "Connect automatically" now opens in a new tab

= 0.3.0 =
* Self-hosted auto-updates via GitHub Releases
* Added GPLv2 license, security policy, and clean-uninstall support

= 0.2.0 =
* Added the connect handshake (automatic + pairing code) and the read-only
  site-summary widget

= 0.1.0 =
* Initial release: tracking snippet + tracking ID setting
