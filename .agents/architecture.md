# Architecture

## Layers

| Layer | Files | Job |
| --- | --- | --- |
| Bootstrap | `isudev-reviews.php`, `includes/class-plugin.php` | Constants, autoloader (`class-*.php` / `interface-*.php` under `includes/`), `Plugin::boot()` on `plugins_loaded`, the SDK instance (`Plugin::cloud()`) and license (`Plugin::license()`) |
| Settings | `class-settings.php` | One option `isurev_settings`; secrets write-only |
| Data | `class-schema.php`, `class-source-repository.php`, `class-review-repository.php` | Two custom tables, upserts, queries with object cache group `isurev` |
| Providers | `includes/providers/*` | `Provider` interface: `cloud` (isuDev API, default), `google_places` (owner's own key, max 5 reviews) and `demo` (30 sample reviews from `assets/demo/reviews.json`, no API; one demo source via `Demo_Provider::ensure_source()`, not counted against the free one-source limit). More via `isurev_providers` |
| Sync | `class-sync-service.php`, `class-cron.php` | Provider → upsert → source meta/status; Pro cron every hour, max 3 due sources per tick |
| Render | `src/blocks/reviews/*`, `class-block.php`, `class-renderer.php`, `class-templates.php`, `templates/*` | Block (apiVersion 3, server-rendered), summary + cards through a template folder |
| Admin | `includes/admin/*` | Menu (Reviews, Sources, Templates, Settings), `admin-post.php` handlers with nonces, list table |
| REST | `class-rest-controller.php` | Editor/admin data: sources, templates, manual sync. SDK adds `site-verify` |
| CLI | `class-cli.php` (+ SDK `register`, `license`) | Everything admins do, scriptable |
| SDK (vendored) | `vendor-prefixed/isudev/plugin-sdk` | Site registration, API client, license cache. Source: `~/Other Projects/isudev-plugin-sdk` |
| Updates | `class-update-checker.php`, `vendor-prefixed/yahnis-elsts/plugin-update-checker` | Self-updates from GitHub release ZIPs (like isudev-consent) |

## Flows

### First API call (registration, SDK)

1. The plugin needs a site token (`isurev_site_token`); none → `Site_Registration::register()`.
2. It stores a one-time nonce (`isurev_verify_nonce`) and calls `POST {api_url}/v1/sites/register` with `site_url = home_url('/')`.
3. The platform calls back `GET {site}/?rest_route=/isudev-reviews/v1/site-verify&nonce=…`; the SDK answers `{"ok": true}` when the nonce matches.
4. The platform returns the token; every later call sends it plus `X-Isurev-Site: home_url('/')`. A token is bound to that host.
5. When the API answers `invalid_site_token`, the client re-registers once.

So the site must be **reachable from the internet** (no `.local`, no basic auth or maintenance mode on the REST route).

### Sync (`Sync_Service::sync`)

1. Source → provider (`cloud` by default) → `is_available()` (Facebook needs Pro).
2. `fetch()` with `limit` (free `Cloud_Provider::FREE_LIMIT` = 8, Pro `PRO_LIMIT` = 1000), `since` (Pro only, after the first full import = `backfilled_at`), `language` (setting, default from locale).
3. Cloud: `POST /reviews/v1/fetch`. The server enforces the real limits (free 8 reviews, 4 fresh imports per site per 30 days, 30 per server IP per day; Pro per plan) and **caches** a place for 7 days (free) or 6 h (paid). Cache hits use no quota; the response has `cached` and `fetched_at`.
4. `Review_Repository::upsert_many()` (unique `source_id + external_id`, keeps `is_hidden`/`is_pinned`), then source meta (`name`, `url`, `rating_avg`, `rating_count`, `last_status`, `last_error`), then `do_action( 'isurev_synced' )`.
5. Errors are stored on the source (`last_status = error`, `last_error`) and returned as `WP_Error`. SDK errors are `isurev_api_<api error code>` (see `operations.md`).

### Licensing

- The license key is a setting; the SDK checks `GET /v1/licenses/check?product=reviews`, caches it 12 h and keeps the last good Pro state through outages.
- Features: `auto_sync`, `facebook`, `unlimited`, `pro_templates`, `multiple_sources` (`Plugin::FEATURES`). Free: one source, no cron, free templates only.
- The UI and cron read `Plugin::license()->has()`; the server enforces the same things again.

### Links (where to buy Pro)

- `Plugin::link( 'pricing' | 'account' | 'docs' | 'download' )` → SDK `Links`: from the platform's public `/v1/catalog` (cached a week, also refreshed by the license check), fallback `Config::$links` in `Plugin::cloud()`. The platform admin (Sales → Products) is the only place to change them; never hard-code a purchase URL.
- Shown as "Get Pro" (Settings, Sources, Plugins screen action link) on free, "Manage your license…" on Pro.

### Render

- `render.php` → `Renderer::render( $attributes )`: normalize attributes, query visible reviews of the chosen sources, summary (average, count) and one `card.php` per review from the resolved template.
- Templates: folder with `template.json` (`title`, `tier`, `layouts`), `card.php`, `style.css`. Without a Pro license a locked (Pro) template falls back to the site default, then to `default` (`Templates::resolve`).
- Card templates only call `Renderer::*` helpers, which return escaped HTML.

## Invariants

- No raw secrets anywhere visible (UI, REST, CLI output, logs).
- The front end never calls the API; pages render from the tables only.
- Upserts never reset admin flags.
- `Schema::DB_VERSION` bumps with any table change; `maybe_upgrade()` runs `dbDelta` on boot.
- Every API error reaches the admin as a readable message on the source.

## Where to change what

| Change | Where |
| --- | --- |
| New review source (Trustpilot…) | New `Provider` class + `isurev_providers` filter; server-side route on the platform if it needs a paid API |
| New template | `templates/<slug>/` (or a theme folder via `isurev_template_dirs`) |
| New setting | `Settings::defaults()`, `includes/admin/views/settings.php`, `Cli::set` docs |
| Table change | `Schema::install()` + bump `DB_VERSION` |
| Free/Pro limits | **Platform** (`ReviewsProduct`, plan versions), then mirror the UI constant (`Cloud_Provider::FREE_LIMIT`) |
| API client, registration, license cache | The SDK repo, then `composer update isudev/plugin-sdk` |
