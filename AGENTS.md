# isuDev Reviews (WordPress plugin)

Repo: https://github.com/IsuDevelopment/wp-reviews (public, branch `main`). Releases are GitHub releases; installed copies update themselves from them.

Google (and, with Pro, Facebook) reviews stored in the site's own tables and shown with the `isudev-reviews/reviews` block. Reviews come from the isuDev cloud API (`papi.isudev.com`, repo `isudev-platform`) or, as a fallback, from the site owner's own Google Places key. Free: quick manual import of the 18 newest Google reviews. **Demo data** (30 sample reviews, no API) for local sites. Pro: full history, auto-sync, Facebook, Pro templates, several sources.

This file is the entry point for **every agent** (Claude Code reads it through `CLAUDE.md`). Everything else lives in [`.agents/`](.agents/README.md).

## Read in this order

1. **This file**: the hard rules.
2. [`.agents/state.md`](.agents/state.md): where the work stands and the next step.
3. [`.agents/code-map.md`](.agents/code-map.md): **generated** map of classes, hooks, REST routes, WP-CLI, options, tables, block and templates. Orient here before opening files.
4. [`.agents/architecture.md`](.agents/architecture.md): how a sync and a render work, invariants, where each change goes.
5. [`.agents/operations.md`](.agents/operations.md): build, lint, local site, test site, **debugging table** (error → cause → fix).
6. [`.agents/decisions.md`](.agents/decisions.md): why things are the way they are (dated, append-only).
7. History and the original plan (Polish): `.agents/plan.md`.

Business rules shared by all isuDev plugins (free vs Pro, licensing, the API contract, Paddle) live in the `isudev-plugins` skill (`~/.claude/skills/isudev-plugins/`). The server side of every API call is in `~/Other Projects/isudev-platform` (its own `AGENTS.md`).

## Hard rules

1. **The plugin is GPL and can be patched, so limits live on the server.** `License::has()` only shapes the UI and cron. Never move a quota or a provider key into the plugin.
2. **Secrets are write-only.** `license_key`, `google_api_key`, the site token: never printed, logged, returned by REST or put in URLs. `wp isurev set` accepts them but never echoes them.
3. **Degrade, don't break.** API down: keep showing stored reviews, keep the last good Pro state (SDK, 1 h retry). The front end never calls the API.
4. **Data in custom tables** (`dbDelta` + `Schema::DB_VERSION`), upserts keep admin flags (`is_hidden`, `is_pinned`). No CPT.
5. **Block is apiVersion 3**, PHP-rendered (`render.php` → `Renderer`), page-cache friendly. Template styles reach the editor iframe through `enqueue_block_assets`.
6. **Extend through filters, not edits:** `isurev_providers` (new source), `isurev_template_dirs` (new skin), `isurev_synced` (after a sync).
7. **`vendor-prefixed/` is generated and committed.** Strauss copies the SDK (prefixed `IsuDev\Reviews\Vendor\`) from `~/Other Projects/isudev-plugin-sdk` and Plugin Update Checker (left unprefixed on purpose: PUC handles several copies itself). Change the SDK in its repo, then `composer update isudev/plugin-sdk` here and commit `vendor-prefixed/`. Never edit it by hand. CI does not run Composer (the SDK is a local path repo).
8. **WordPress coding standards.** `composer check` (phpcs + code-map check) and `npm run build` must pass before you hand back.
9. **Record everything in the same change:** `composer code-map`, `.agents/state.md` (what is done, what is next), `.agents/decisions.md` for every decision. A change without these is not done.

## Commands

```bash
composer install            # also runs Strauss → vendor-prefixed/
composer check              # phpcs (WPCS) + code-map --check
composer lint:fix           # phpcbf
composer code-map           # regenerate .agents/code-map.md
npm run build               # block → build/ (Node 22, see .nvmrc)
npm run lint:js && npm run lint:css
npm run plugin-zip          # zip from the package.json "files" list
git tag vX.Y.Z && git push --tags   # release (see operations.md → Release)

wp isurev sources | source-add <google|facebook> <id> [--provider=cloud|google_places] [--no-sync] | source-delete <id>
wp isurev sync <id> | sync --all
wp isurev set <key> <value>  # api_url, license_key, google_api_key, language
wp isurev demo              # demo source with 30 sample reviews (no API, works on .local)
wp isurev register | license # from the SDK
```
