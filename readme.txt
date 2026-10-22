=== IsuDev Reviews ===
Contributors: isudev
Tags: reviews, google reviews, facebook reviews, testimonials, block
Requires at least: 6.9
Tested up to: 7.1
Requires PHP: 8.2
Stable tag: 0.2.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Google and Facebook reviews stored in your own database and shown with a fast, server-rendered block.

== Description ==

IsuDev Reviews imports reviews of your business into custom tables on your site and shows them with the **Reviews** block (grid or carousel, several templates, rating summary). Pages render from your database only, so they stay fast and work with full-page caching.

* **Free:** manual import of the 18 newest Google reviews through the isuDev cloud API, one source, free templates.
* **Pro:** full review history, automatic sync, Facebook reviews, Pro templates and several sources.
* **Demo data:** 30 sample reviews you can import without any API, also on local development sites (Reviews → Sources → Import demo reviews, or `wp isurev demo`).
* Your own Google Places API key works as an alternative source (Google returns at most 5 reviews).
* WP-CLI: `wp isurev sources|source-add|source-delete|sync|set|demo|register|license`.

The cloud import needs a publicly reachable site: the isuDev API verifies the domain by calling the plugin's REST route once.

Updates arrive from GitHub releases through the built-in updater. Define `ISUREV_DISABLE_SELF_UPDATES` as `true` or use the `isurev_self_updates_enabled` filter to turn this off.

== Changelog ==

= 0.2.0 =
* Demo data: 30 sample reviews without any API, for local sites and template testing.
* Free import is now the 18 newest reviews.
* Self-updates from GitHub releases.
* Fix: the release package includes the bundled SDK.

= 0.1.0 =
* First working version: cloud import (free and Pro), Google Places provider, Reviews block with templates, admin screens, WP-CLI.
