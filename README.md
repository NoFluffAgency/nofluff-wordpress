# No Fluff Analytics for WordPress

Connects a WordPress site to the [No Fluff dashboard](https://app.nofluff.agency):
adds the cookieless tracking script, reports form submissions and
WooCommerce orders as goal events, keeps logged-in editors out of the
numbers, and survives the common caching and optimisation plugins.

**Download:** [latest release](https://github.com/NoFluffAgency/nofluff-wordpress/releases/latest/download/nofluff-analytics.zip)
→ WordPress → Plugins → Add New → Upload Plugin → Settings → No Fluff → paste
the site ID from the dashboard.

## What it does

| Setting | Default | Effect |
|---|---|---|
| Site ID | – | Adds `<script defer src="https://app.nofluff.agency/nf.js" data-site="…">` to the head |
| Do not count logged-in editors | on | Users with `edit_posts` are not tracked |
| Form submissions | on | `form_submit` event on success in Contact Form 7, WPForms, Gravity Forms, Elementor Pro (plugin + form id only) |
| WooCommerce orders | on | One `purchase` event per order (total + currency only) |

Also: exclusion attributes and filters for WP Rocket, Autoptimize, LiteSpeed
Cache, SiteGround Optimizer, NitroPack and Cloudflare Rocket Loader; a
suggested section in Settings → Privacy; German translation; uninstall
removes all settings.

Hooks: `NOFLUFF_ANALYTICS_HOST` constant or `nofluff_analytics_host` filter
(other region), `nofluff_analytics_should_track` filter (e.g. staging).

## Requirements

WordPress 6.3+ (script loading strategies), PHP 7.4+. The German
translation needs WordPress 6.5+ (PHP translation files).

## Releasing

1. Bump the version in three places: the `Version:` header and
   `NOFLUFF_ANALYTICS_VERSION` in `nofluff-analytics.php`, and
   `Stable tag` in `readme.txt`. Add a changelog entry.
2. Merge to `main`, then tag: `git tag v1.0.1 && git push origin v1.0.1`.
3. CI checks that the three versions match the tag, builds
   `nofluff-analytics.zip` and publishes the release. Sites with the plugin
   see the update in WordPress within twelve hours.

## WordPress.org, later

Remove `includes/updater.php`, its `require`, and the `Update URI` header
(directory plugins must not update themselves), then submit. Everything
else is written to the directory guidelines and checked by Plugin Check in
CI.

## License

GPL-2.0-or-later, see [LICENSE](LICENSE).
