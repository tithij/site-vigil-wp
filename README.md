# Site Vigil for WordPress

Lightweight visitor tracking and uptime monitoring for WordPress, powered by [Site Vigil](https://site-vigil.com).

Every uptime, SSL-expiry, and domain-expiry check originates from Site Vigil's own Cloudflare Workers — this plugin never checks the site itself. It injects a small privacy-friendly visitor-tracking snippet and, once connected, shows a read-only summary (status, uptime, last incident, SSL/domain expiry, traffic) of what Site Vigil already knows about your site, with a link through to the full dashboard.

**Requires a [Site Vigil](https://app.site-vigil.com) account.** This plugin is a client, not a standalone monitoring tool — there's nothing to configure locally beyond connecting it to your account.

## Requirements

- WordPress 5.8 or later
- PHP 7.4 or later
- A free Site Vigil account — you don't need to add the site in the dashboard first; connecting from the plugin can create it for you

## Install

1. Download the latest release zip from [Releases](https://github.com/tithij/site-vigil-wp/releases/latest).
2. In wp-admin, go to **Plugins → Add New → Upload Plugin** and upload the zip. (Do not clone/download this repository directly — the release zip is the built, install-ready package.)
3. Activate the plugin, then go to **Settings → Site Vigil**.
4. Click **Connect automatically** — you'll be sent to Site Vigil to approve the connection and pick or create the site, then bounced back to wp-admin. No password is needed there. If the redirect doesn't work, expand **Connect manually** and paste a pairing code generated from **Administration → Sites** (select the site) → **CMS Plugin** on the dashboard instead. Either way, your tracking ID is set automatically — there's nothing to copy by hand.

Once a release is published, this plugin checks GitHub for updates and will show a normal wp-admin update notification — no need to manually re-download.

## License

GPLv2 or later — see [LICENSE](LICENSE).

## Reporting issues

Bugs and feature requests: [open an issue](https://github.com/tithij/site-vigil-wp/issues). For security issues, see [SECURITY.md](SECURITY.md) instead of filing a public issue.
