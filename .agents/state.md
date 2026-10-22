# Plugin state

**The handover file.** Read after `AGENTS.md`; update at the end of every task (replace stale lines, history lives in `decisions.md`).

_Last updated: 2026-10-22_

## Where we are

- Version `0.2.0`, repo `IsuDevelopment/wp-reviews` (public), releases with self-update (2026-10-22). Works end to end against **live** `papi.isudev.com` with real Google reviews (SerpApi): tested 2026-10-22 on `https://test.isuke.pl` (registered, IsuDev place synced: 4 reviews, 5.0) and once from `isudev-library.local` through a tunnel.
- Verified earlier in WP 7.1 Playground (`.agents/plan.md` §6): free import, dedupe, free → Pro backfill then incremental, list with hide/show, template gallery, block in the iframed editor, grid and carousel, mobile.
- Free = 18 newest Google reviews per manual import (2 SerpApi searches), one source, free templates. Demo data (30 sample reviews, `wp isurev demo` or the Sources page button) works without the API, also on `.local`. Pro = full history, auto-sync, Facebook (needs the platform's Graph token, step 7 on the platform), Pro templates, several sources.
- Lint: phpcs (WPCS) clean; ESLint/Stylelint set up (`npm run lint:*`).

## Open items

- Commercial launch (landing, CTA, Pro checkout in the plugin) waits until the plugin is usable; for now it ships as public GitHub releases.
- No CI lint job: Composer cannot install in CI while the SDK is a local path repo. Publishing `isudev-plugin-sdk` to GitHub would allow it.
- Facebook needs the platform's OAuth connection (platform build order step 7).
- Plugin-side Pro checkout (buy Pro from wp-admin) is not built; licenses come from the platform admin for now.
- The `google_places` provider (owner's own key) returns at most 5 reviews; Google Business Profile API for owners is a candidate free source (not started, needs Google's approval).
- Local `isudev-library.local` still has the token of a removed tunnel host; it re-registers by itself only where the host is public. Test against live on `test.isuke.pl`.

## Next step for an agent

1. Ask the user what to work on; candidates above.
2. Before handing back: `composer check`, `npm run build`, deploy to `test.isuke.pl` (operations.md) and run `wp isurev sync 1` there.
