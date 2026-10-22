# .agents: shared context for Claude Code and Codex

`AGENTS.md` (plugin root) is the entry point with the hard rules. `CLAUDE.md` only imports it.

| File | What | Read when |
| --- | --- | --- |
| `state.md` | Where the work stands, open items, next step | **First**, every task; updated at the end of every task |
| `code-map.md` | **Generated** (`composer code-map`): classes, hooks, REST, WP-CLI, options, tables, block, templates | Before touching code |
| `architecture.md` | Boot, sync and render flows, licensing, invariants, where to change what, recipes | Any code change |
| `operations.md` | Build, lint, local site, public test site, testing against the platform, **debugging table** | Running, shipping or debugging |
| `decisions.md` | Dated, append-only decision log | Before changing anything that looks deliberate |

## Keeping it true

- `composer check` fails on a stale `code-map.md`; regenerate with `composer code-map`.
- `state.md` and `decisions.md` change in the same change as the work.
- An error you had to work out by hand goes into the debugging table in `operations.md`.
