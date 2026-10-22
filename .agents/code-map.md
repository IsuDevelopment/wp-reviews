# Code map

**Generated** by `php bin/code-map.php` (`composer code-map`). Do not edit by hand; `composer check` fails when it is stale.

## PHP classes (`includes/`, namespace `IsuDev\Reviews`)

| File | Type | Summary | Public methods |
| --- | --- | --- | --- |
| `includes/admin/class-admin.php` | class `Admin` | Menu, form handlers (admin-post.php) and notices. | `register`, `menu`, `assets`, `page_reviews`, `page_sources`, `page_templates`, `page_settings`, `handle_add_source`, `handle_add_demo`, `handle_sync_source`, `handle_delete_source`, `handle_save_settings`, `handle_set_template`, `handle_review_visibility`, `handle_bulk`, `notice` |
| `includes/admin/class-reviews-list-table.php` | class `Reviews_List_Table` | All stored reviews with source filter and hide/show. | `get_columns`, `prepare_items`, `single_row`, `no_items` |
| `includes/admin/views/reviews.php` | file | View: all reviews. | |
| `includes/admin/views/settings.php` | file | View: settings and license. | |
| `includes/admin/views/sources.php` | file | View: sources. | |
| `includes/admin/views/templates.php` | file | View: templates gallery with live previews on sample data. | |
| `includes/class-block.php` | class `Block` | Registers isudev-reviews/reviews from build/ and the template styles. | `register`, `register_block` |
| `includes/class-cli.php` | class `Cli` | Manage review sources and syncs. `register` and `license` come from the isuDev plugin SDK. | `register`, `sources`, `source_add`, `demo`, `source_delete`, `sync`, `set` |
| `includes/class-cron.php` | class `Cron` | Hourly tick that syncs sources whose interval has passed. | `register`, `schedule`, `unschedule`, `tick` |
| `includes/class-plugin.php` | class `Plugin` | Plugin bootstrap. | `boot`, `activate`, `deactivate`, `sync_service`, `cloud`, `license` |
| `includes/class-renderer.php` | class `Renderer` | Renders the wrapper, summary and one card per review through the chosen template. Card templates call the static helpers below; every helper returns escaped HTML. | `normalize`, `render`, `summary_data`, `stars`, `avatar`, `date`, `text`, `reply`, `platform_icon` |
| `includes/class-rest-controller.php` | class `Rest_Controller` | Namespace isudev-reviews/v1. The SDK adds site-verify to the same namespace. | `register`, `routes`, `sources`, `templates`, `sync` |
| `includes/class-review-repository.php` | class `Review_Repository` | Reads and upserts reviews. | `upsert_many`, `query`, `count`, `set_hidden` |
| `includes/class-schema.php` | class `Schema` | Creates and migrates the plugin tables with dbDelta. | `sources_table`, `reviews_table`, `maybe_upgrade`, `install`, `uninstall` |
| `includes/class-settings.php` | class `Settings` | One option array. Secrets (license_key, google_api_key) are write-only in the UI. | `defaults`, `all`, `get`, `save`, `set_default_template` |
| `includes/class-source-repository.php` | class `Source_Repository` | CRUD for review sources (one Google profile or Facebook page each). | `all`, `find`, `create`, `update`, `delete`, `due_for_sync` |
| `includes/class-sync-service.php` | class `Sync_Service` | Provider → upsert → source meta and status. | `sync` |
| `includes/class-templates.php` | class `Templates` | A template is a folder with template.json, card.php and style.css. Themes or add-ons can register more folders through the isurev_template_dirs filter. | `all`, `is_locked`, `resolve`, `style_handle`, `register_styles`, `enqueue_all_styles` |
| `includes/class-update-checker.php` | class `Update_Checker` | Registers Plugin Update Checker against the release ZIPs of the public GitHub repo (same setup as isudev-consent). Skipped in a git checkout, on Composer-managed sites, with ISUREV_DISABLE_SELF_UPDATES or when the isurev_self_updates_enabled filter says no. | `register`, `should_register` |
| `includes/providers/class-cloud-provider.php` | class `Cloud_Provider` | Fetches through the isuDev Reviews API. Free tier: Google, 8 newest, 4 fresh imports per 30 days (server-side limits; repeats within 7 days come from the API cache and are free). Pro: full history, Facebook. | `id`, `label`, `platforms`, `is_available`, `fetch` |
| `includes/providers/class-demo-provider.php` | class `Demo_Provider` | Sample reviews from assets/demo/reviews.json, no API and no license. For local sites (which cannot register with the cloud API) and for trying templates and layouts. Always returns all sample reviews: the free limit belongs to the cloud API. | `id`, `label`, `platforms`, `is_available`, `fetch`, `ensure_source` |
| `includes/providers/class-fetch-result.php` | class `Fetch_Result` | Normalized reviews plus source meta (name, url, average, total). |  |
| `includes/providers/class-google-places-provider.php` | class `Google_Places_Provider` | Direct call with the site owner's own API key. Google returns at most 5 reviews per place. | `id`, `label`, `platforms`, `is_available`, `fetch` |
| `includes/providers/class-provider-registry.php` | class `Provider_Registry` | Lists providers. Add more (Trustpilot, Booking…) with the isurev_providers filter. | `all`, `get` |
| `includes/providers/interface-provider.php` | interface `Provider` | A provider fetches normalized reviews for one source. See .agents/plan.md for the review shape. | `id`, `label`, `platforms`, `is_available`, `fetch` |
| `isudev-reviews.php` | file | Plugin header, constants, autoloader, boot. Google and Facebook reviews stored locally and shown with Gutenberg blocks. Free quick import, Pro auto-sync and templates. | |

## Hooks

Own hooks (`isurev_*`) are the extension points; `add_*` rows show what the plugin attaches to.

| Kind | Hook | Where |
| --- | --- | --- |
| add_action | `'admin_enqueue_scripts'` | `includes/admin/class-admin.php` |
| add_action | `'admin_menu'` | `includes/admin/class-admin.php` |
| add_action | `'admin_notices'` | `includes/admin/class-admin.php` |
| add_action | `'admin_post_isurev_' . $action` | `includes/admin/class-admin.php` |
| add_action | `'enqueue_block_assets'` | `includes/class-block.php` |
| add_action | `'init'` | `includes/class-block.php` |
| add_action | `'load-' . $hook` | `includes/admin/class-admin.php` |
| add_action | `'plugins_loaded'` | `isudev-reviews.php` |
| add_action | `'rest_api_init'` | `includes/class-rest-controller.php` |
| add_action | `self::HOOK` | `includes/class-cron.php` |
| apply_filters | `'isurev_providers'` | `includes/providers/class-provider-registry.php` |
| apply_filters | `'isurev_self_updates_enabled'` | `includes/class-update-checker.php` |
| apply_filters | `'isurev_template_dirs'` | `includes/class-templates.php` |
| do_action | `'isurev_synced'` | `includes/class-sync-service.php` |

## REST routes (plugin, for the editor and admin)

| Method | Route | File |
| --- | --- | --- |
| `WP_REST_Server::READABLE` | `/isudev-reviews/v1/sources` | `includes/class-rest-controller.php` |
| `WP_REST_Server::READABLE` | `/isudev-reviews/v1/templates` | `includes/class-rest-controller.php` |
| `WP_REST_Server::CREATABLE` | `/isudev-reviews/v1/sources/(?P<id>\d+)/sync` | `includes/class-rest-controller.php` |
| `GET` | `/isudev-reviews/v1/site-verify` | SDK (`vendor-prefixed/isudev/plugin-sdk`), called by the platform during registration |

## WP-CLI (`wp isurev`)

- Plugin: `sources`, `source-add`, `demo`, `source-delete`, `sync`, `set` (`includes/class-cli.php`).
- SDK: `register`, `license`.
- Help: `wp help isurev <command>`.

## Options and tables

- `isurev_settings`: `api_url`, `license_key`, `google_api_key`, `default_template`, `language`. Secrets (`license_key`, `google_api_key`) are write-only in the UI and CLI.
- `isurev_db_version`: schema version (`Schema::DB_VERSION`).
- SDK: `isurev_site_token`, `isurev_license` (license cache), `isurev_verify_nonce` (transient during registration).
- Table `{prefix}isurev_sources`: id, platform, provider, external_id, name, url, rating_avg, rating_count, sync_interval, last_synced_at, backfilled_at, last_status, last_error, created_at, updated_at.
- Table `{prefix}isurev_reviews`: id, source_id, platform, external_id, author_name, author_url, author_avatar, rating, recommendation, content, language, review_url, reply_content, published_at, is_hidden, is_pinned, created_at, updated_at.

## Block

- `isudev-reviews/reviews` (apiVersion 3), source `src/blocks/reviews/`, built to `build/` by `npm run build`, rendered by `render.php` through `Renderer`.
- Attributes: `sourceIds`, `template`, `layout`, `columns`, `limit`, `minRating`, `textLines`, `showSummary`, `withText`.

## Templates (`templates/<slug>/`: `template.json`, `card.php`, `style.css`)

- `default`: Classic card, tier free, layouts grid/slider.
- `spotlight`: Spotlight, tier pro, layouts grid/slider.

