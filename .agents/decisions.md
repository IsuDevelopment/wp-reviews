# Decision log

Newest first, append-only: a changed decision gets a new entry that says what it replaces. Earlier history (2026-10-15 → 10-19, Polish) is in `.agents/plan.md`; cross-plugin decisions are in the `isudev-plugins` skill.

Format: `## YYYY-MM-DD: title`, then **Decision**, **Why**, **Decided by**, **Affects**.

## 2026-10-22: SDK from its own public repo, CI on every push

- **Decision:** `isudev/plugin-sdk` comes from `https://github.com/IsuDevelopment/isudev-plugin-sdk` (public, tags `vX.Y.Z`, dev files `export-ignore`d) instead of a local path repo. CI runs Composer (with Strauss), checks that `vendor-prefixed/` is committed and current, then phpcs, code map, ESLint, Stylelint and the build.
- **Why:** a path repo only works on one machine; the SDK code ships inside the public plugin anyway.
- **Decided by:** user.
- **Affects:** `composer.json` `repositories`, `composer.lock`, `vendor-prefixed/`, `.github/workflows/ci.yml`.

## 2026-10-22: Public repo with GitHub releases and self-updates

- **Decision:** the plugin lives in the public repo `IsuDevelopment/wp-reviews`; tags `vX.Y.Z` build a ZIP release (`release.yml`), and installs update themselves through Plugin Update Checker, as `isudev-consent` does. `vendor-prefixed/` is committed (CI cannot install the local SDK path repo). Version 0.2.0 is the first release.
- **Why:** the user wants the plugin on Git now and a real commercial launch (CTA, payments) later. GPL code exposes nothing valuable: limits and provider keys are on the server. A private repo would need a token on every site.
- **Decided by:** user (public, updater like isudev-consent).
- **Affects:** `.github/workflows/release.yml`, `includes/class-update-checker.php`, `Plugin::boot`, `composer.json` (PUC, Strauss `exclude_from_prefix`), `.gitignore`, `readme.txt`, `package.json` `files`.

## 2026-10-22: Demo data provider

- **Decision:** provider `demo` serves 30 sample reviews from `assets/demo/reviews.json` (fictional business, dates relative to the import day). Sources page button and `wp isurev demo`. Ignores the license and the free one-source limit; it never calls the API.
- **Why:** local sites cannot register with the cloud API, so nobody could try the plugin locally.
- **Decided by:** user.
- **Affects:** `includes/providers/class-demo-provider.php`, `Provider_Registry`, `Admin::handle_add_demo`, `views/sources.php`, `Cli::demo`, `assets/demo/reviews.json`.

## 2026-10-22: Free import is 18 reviews

- **Decision:** `Cloud_Provider::FREE_LIMIT` 10 → 18, matching the platform.
- **Why:** SerpApi's first page is 8 reviews and the next 20, so 18 costs the same two searches as 10.
- **Decided by:** user.
- **Affects:** `class-cloud-provider.php`.

## 2026-10-22: Agent files for the plugin

- **Decision:** `AGENTS.md` + `CLAUDE.md` and `.agents/` (state, generated code map, architecture, operations with a debugging table, decisions), like `isudev-platform`. `composer check` = phpcs + code-map check.
- **Why:** an agent should continue the plugin from the repo alone, without debugging it from scratch.
- **Decided by:** user.
- **Affects:** `AGENTS.md`, `CLAUDE.md`, `.agents/*`, `bin/code-map.php`, `composer.json`.

## 2026-10-22: Free import is the 10 newest reviews, cached on the server

- **Decision:** `Cloud_Provider::FREE_LIMIT` 20 → 10 to match the platform. The platform caches a fetch of the same place for 72 h (free) or 6 h (paid) across all sites.
- **Why:** SerpApi's free plan is 250 searches a month; one insistent free user must not use it up.
- **Decided by:** user.
- **Affects:** `includes/providers/class-cloud-provider.php`; platform `FetchReviewsController`, `ReviewsProduct`.

## 2026-10-22: Package includes vendor-prefixed

- **Decision:** `vendor-prefixed` is in the `package.json` `files` list (used by `plugin-zip` and the test-site rsync).
- **Why:** the plugin requires `vendor-prefixed/autoload.php`; a zip without it is fatal.
- **Decided by:** agent (bug fix).
- **Affects:** `package.json`.

## 2026-10-19: Cloud plumbing comes from the shared SDK

- **Decision:** `Api_Client`, `Site_Registration`, `License` and the `register`/`license` CLI live in `isudev/plugin-sdk`, vendored with Strauss under `IsuDev\Reviews\Vendor\`.
- **Why:** every isuDev plugin shares the same API contract.
- **Decided by:** user.
- **Affects:** `class-plugin.php` (`Plugin::cloud()`), `composer.json`, `vendor-prefixed/`.
