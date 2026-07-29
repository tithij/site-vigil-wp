=== Site Vigil ===
Contributors: sitevigil
Tags: monitoring, uptime, analytics
Requires at least: 5.8
Tested up to: 6.4
Requires PHP: 7.4
Stable tag: 0.5.1
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
3. Go to Settings → Site Vigil and connect the plugin to your Site Vigil
   account — either "Connect automatically" or with a pairing code. Your
   tracking ID is set automatically; there's nothing to copy by hand.

== Changelog ==

= 0.5.1 =
* Replaced the placeholder shield brandmark in the settings-page header with
  the actual Site Vigil logo (the pulse-line mark)
* Removed client-side handling for a 429 rate-limit response that the
  backend no longer sends

= 0.5.0 =
* Added a wp-admin Dashboard widget showing the same live status card as
  the settings page (status badge, stats, incident line) — same cached
  summary, no extra API calls
* Not-connected/disconnected states show a minimal prompt linking to
  Settings instead of duplicating the full connect flow
* Refresh/Disconnect now return you to whichever screen you clicked them
  from (Dashboard or Settings) instead of always landing on Settings

= 0.4.5 =
* Fixed a timezone bug in every "X ago" label ("Checked...", "Since...",
  "All clear for..."): they were diffing a true-UTC API timestamp against
  WordPress's current_time('timestamp'), which is silently shifted by the
  site's UTC offset — on a UTC+2 site this added a spurious ~2 hours to
  every displayed duration, even on data fetched moments ago. Now uses
  time() (true UTC) throughout, matching strtotime()'s output.

= 0.4.4 =
* Shortened the client-side summary cache from 5 to 4 minutes, matching
  get-site-summary's own per-token rate limit exactly — a settings-page
  reload now always gets the freshest data the backend allows, instead of
  padding an extra minute of staleness on top

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
