# Security Policy

If you find a security vulnerability in this plugin, please report it privately rather than opening a public issue.

**Contact:** hello@site-vigil.com

Please include:
- The plugin version affected
- Steps to reproduce
- Impact (what an attacker could do)

We'll acknowledge your report and let you know a timeline for a fix. Please give us a reasonable window to release a patch before any public disclosure.

## Scope

This plugin talks to Site Vigil's Edge Functions using a per-site `plugin_token` (see the connector code in `includes/class-connector.php`). It never handles a Site Vigil account password or session, and it never runs any check against the site itself — all monitoring originates from Site Vigil's Cloudflare Workers.
