# Operations

## Release

1. Bump the version in `isudev-reviews.php` (header and `const VERSION`), `readme.txt` (`Stable tag` + a `= X.Y.Z =` changelog section) and `package.json`.
2. `composer check`, `npm run build`, commit to `main`, push.
3. `git tag vX.Y.Z && git push origin vX.Y.Z` (or run the workflow by hand). `.github/workflows/release.yml` validates the versions, builds the block, zips the `package.json` `files` list as `isudev-reviews-X.Y.Z.zip` and creates the GitHub release with the changelog.
4. Sites with the plugin installed from a ZIP see the update in Dashboard → Updates within 12 h (Plugin Update Checker, `Update_Checker`). It is skipped in a git checkout (the `.local` symlink), on Composer-managed sites, with `ISUREV_DISABLE_SELF_UPDATES` or the `isurev_self_updates_enabled` filter.

## Local development

- Source: `~/Other Projects/isudev-review/isudev-reviews`, git repo `IsuDevelopment/wp-reviews`.
- Local site: **`isudev-library.local`** (Local by Flywheel, `~/Local Sites/isudev-library/app/public`), where the user tests all plugins. The plugin folder there is a **symlink** to the source, so edits are live.
- WP-CLI on the Local site: `cd ~/Local\ Sites/isudev-library/app/public && wp isurev …` (needs the site running in Local). The Homebrew wp-cli prints PHP 8.4 deprecation noise; filter with `2>&1 | grep -vi deprecat`.
- Node 22 (`.nvmrc`): `export PATH="$(ls -d ~/.nvm/versions/node/v22* | tail -1)/bin:$PATH"`.
- After changing PHP: `composer check`. After changing `src/`: `npm run build` (the Local site uses `build/`).

## Demo data (no API)

Local sites cannot register with the cloud API, so use the demo source: admin → Reviews → Sources → **Import demo reviews**, or `wp isurev demo`. It imports 30 fictional reviews (`assets/demo/reviews.json`: ratings 2–5, Polish/English/Norwegian, some without text, some with owner replies, dates spread over the last 8 months). As a Facebook source (`wp isurev source-add facebook isurev-demo --provider=demo`) the stars become recommendations. Edit the JSON to test edge cases; delete the demo source when done.

## API environments

| `api_url` | Use |
| --- | --- |
| `https://papi.isudev.com` (default, `ISUREV_API_URL`) | Live platform; SerpApi is real, the monthly quota is shared |
| `https://stg-papi.isudev.com` | Stage platform (mock adapter unless the user configured SerpApi there) |
| Local platform (`php -S` from `isudev-platform/public`, `PLATFORM_ALLOW_PRIVATE_HOSTS=true`) | Fully local; see the platform's `AGENTS.md` |

The saved setting wins over the constant: an old value (`http://127.0.0.1:8787`, the POC) breaks every call. Fix: `wp isurev set api_url https://papi.isudev.com` (this also drops the site token, so the site re-registers).

## Public test site (against live)

`https://test.isuke.pl` on SEO Host h57 (`ssh h57`, path `~/domains/test.isuke.pl/public_html`, `wp` available, WP 7.1). The plugin there is registered with live and has the IsuDev place (`ChIJJWfizT6_NAsRMserH5kaZKA`) as source 1.

Deploy the current source there:

```bash
cd ~/Other\ Projects/isudev-review/isudev-reviews
npm run build
rsync -az --delete --relative isudev-reviews.php uninstall.php includes templates assets build languages vendor-prefixed \
  h57:domains/test.isuke.pl/public_html/wp-content/plugins/isudev-reviews/
ssh h57 'cd ~/domains/test.isuke.pl/public_html && wp isurev sync 1'
```

The file list is the `package.json` `files` list; keep both in sync.

A `.local` site cannot register with live (the platform cannot call it back). For a one-off test from `.local` see the platform's `.agents/operations.md` → "Testing a plugin against live" (Cloudflare quick tunnel + temporary `home_url` filter under WP-CLI).

## Checking the server side

From `~/Other Projects/isudev-platform`:
- `bin/remote live report` (per host: hits, errors, upstream calls) and `bin/remote live health` (`upstream.serpapi` shows SerpApi searches left).
- Admin panel: `accounts.isudev.com/admin` → API → Usage / Requests.

## Debugging: error → cause → fix

SDK errors are `isurev_api_<code>` where `<code>` is the platform's error code. The message is stored on the source (`wp isurev sources` → `status error`, admin → Sources).

| Message or code | Cause | Fix |
| --- | --- | --- |
| `cURL error 7: Failed to connect to 127.0.0.1 port 8787` | Saved `api_url` from the POC | `wp isurev set api_url https://papi.isudev.com` |
| `site_unreachable`: "The site must be publicly reachable" | Host is `.local`/private or does not resolve publicly | Use the public test site, or the tunnel recipe |
| `site_verification_failed` (HTTP n) | The platform reached the site but `site-verify` did not answer `{"ok":true}`: plugin inactive, REST blocked (security plugin, basic auth, maintenance), redirect to another host | Check `curl '{site}/?rest_route=/isudev-reviews/v1/site-verify&nonce=x'` returns JSON `{"ok":false}`; then `wp isurev register` |
| `site_token_required` / `invalid_site_token` | No token or the platform forgot it | The client re-registers once by itself; else `wp isurev register` |
| `site_blocked` | Admin blocked the host on the platform | Platform: `bin/remote live site:block <host> --unblock` (user's OK) |
| `rate_limited` | Free: 5 fetches per site per day or 30 per server IP per day; paid: plan's `fetch_per_day` | Wait, or Pro. Repeats within the cache window are cheap but still count |
| `pro_required` | Facebook without Pro | License with `facebook` |
| `not_configured` (503) | Platform runs the live adapter without the provider key | Platform `.env` (user) |
| `upstream_error` (502) | SerpApi/Graph failed (wrong place ID, quota used up, outage) | Check the place ID; `bin/remote live health` for SerpApi searches left |
| `isurev_api_invalid` "Unexpected response (HTTP n)" | Non-JSON answer: wrong `api_url`, proxy, server error page | Check `api_url`; `curl -s {api_url}/v1/plans` should be JSON |
| `isurev_register_failed` | Registration answered without a token | Platform logs: `bin/remote live logs` |
| `isurev_pro_required` | Facebook source without the feature (plugin-side check) | License |
| Source synced but no new reviews | Free re-fetches the newest 18 and dedupes; `cached: true` within 72 h | Expected |
| Block shows nothing | All reviews hidden, `minRating` too high, `withText` filter, wrong `sourceIds`, or `build/` missing | Check attributes; `npm run build` |
| Pro template renders as Classic | No `pro_templates` feature | Expected without Pro (`Templates::resolve`) |
| Cron never syncs | No `auto_sync`, interval `off`, or WP-Cron disabled | `wp cron event list`, `wp isurev sources` (interval) |

Find the place ID of a Google business: Google's Place ID finder, or the `place_id` in a Maps URL.
