# JPKCom Gutenberg Image Block Alt-Attribute – Developer Reference

## Plugin Overview

SEO helper that keeps the `alt` attribute of rendered `core/image` blocks in sync with the attachment's stored alt text. On every front-end render it reads `_wp_attachment_image_alt` for the image's attachment ID and rewrites the `alt="…"` in the block HTML.

- **Text Domain:** `jpkcom-gutenberg-img-alt`, `Domain Path: /languages` — both added in 1.1.0, because the abilities brought the first translatable strings this plugin ever had
- **Min PHP:** 8.3 | **Min WP:** 7.0
- **Network:** not network-only (no `Network:` header)

---

## Architecture

```
Main file (jpkcom-gutenberg-img-alt.php)
├── declare(strict_types=1)
├── Plugin header
├── JPKCOM_GUTENBERG_IMG_ALT_VERSION constant
├── init @ priority 5: boot JPKComGitPluginUpdater
└── render_block_core/image filter (priority 10, 2 args):
    attrs.id → get_post_meta(_wp_attachment_image_alt)
    → string, not empty → WP_HTML_Tag_Processor sets alt on the first <img>
```

The callback is typed `( string $block_content, array $block ): string`. The attachment id is cast to `int`.

### Why the HTML Tag Processor and not a regular expression (since 1.2.0)

Up to 1.1.0 the replacer was `/(<img[^>]+alt=")[^"]*("[^>]*>)/`. It worked on core's own markup and
failed on anything else, silently: the greedy `[^>]+` ran on to the **last** `alt="` and overwrote
`data-alt` instead of `alt`; a `>` inside another attribute value, a single-quoted or bare `alt`, or an
upper-case `ALT` meant no match at all; an `<img` inside a comment or attribute value was taken for the
real one; and an image without `alt` could never get one. `WP_HTML_Tag_Processor` parses like a browser
and avoids all of that, and `set_attribute()` writes the value literally with its own escaping (it
also escapes an `&` that already looks like an entity, which `esc_attr()` did not). Only the first
`<img>` is changed — `core/image` renders exactly one. `tests/test-alt-replacement.php` holds a case for
each of these; the old regex fails 9 of its 16 cases.

---

## Constants

| Constant | Value | Purpose |
|----------|-------|---------|
| `JPKCOM_GUTENBERG_IMG_ALT_VERSION` | matches the header `Version:` | Plugin version (sync with header/README/phpdoc.xml) |

---

## File Structure

```
jpkcom-gutenberg-img-alt/
├── jpkcom-gutenberg-img-alt.php  ← Main: header, constant, render_block filter, updater bootstrap
├── includes/
│   ├── abilities.php             ← Abilities API: list-images-missing-alt
│   └── class-plugin-updater.php  ← GitHub auto-updater (namespace: JPKComGutenbergImgAltGitUpdate)
├── tests/
│   ├── bootstrap-html-api.php    ← Loads core's real WP_HTML_Tag_Processor (see "Tests")
│   └── test-*.php                ← Run by CI, excluded from the release ZIP
├── .github/workflows/ci.yml      ← Lint, guards, tests (PRs + pushes to main)
├── .github/workflows/release.yml ← Build ZIP, manifest, PHPDoc, deploy to gh-pages (on tag push)
├── phpdoc.xml                    ← phpDocumentor config
├── README.md                     ← Public readme (source for the WP plugin modal)
├── CLAUDE.md                     ← This file
├── LICENSE                       ← GPL-2.0-or-later
└── .gitignore
```

---

## Plugin Updater

- **Namespace:** `JPKComGutenbergImgAltGitUpdate\JPKComGitPluginUpdater`
- **Manifest URL:** `https://jpkcom.github.io/jpkcom-gutenberg-img-alt/plugin_jpkcom-gutenberg-img-alt.json`
- Shared JPKCom updater (downstream copy of the upstream `jpkcom-post-filter` updater; do not edit per-plugin). SHA256 verification, `wp_safe_remote_get()`, URL validation, race-condition lock, 24 h cache, timing-safe `hash_equals()`. Checksum verification is **mandatory**: a missing or unfetchable `checksum_sha256` aborts the update instead of installing unverified code. The verified temp file is returned from `upgrader_pre_download`, so WordPress installs exactly the bytes that were hashed (no second download). Failed manifest fetches are negatively cached for 1 h.
- Hooks: `plugins_api`, `site_transient_update_plugins`, `upgrader_process_complete`, `upgrader_pre_download`.

---

## Release Workflow

**Actions are pinned to commit SHAs.** Every `uses:` line in `.github/workflows/` references a 40-character commit SHA instead of a tag (`@v4`), with the version as a trailing comment. A tag is a movable pointer and can be repointed; a SHA cannot. Since the release workflow builds the plugin ZIP **and** the SHA256 checksum the auto-updater trusts, a compromised action would ship a tampered ZIP together with a matching checksum — the checksum secures the transport, the pinning secures the build. `.github/dependabot.yml` keeps the pins current weekly in one combined PR; when updating, always change the SHA *and* the version comment together.

**CI** (`.github/workflows/ci.yml`) runs on every pull request *and* on every push to `main` — a required status check only covers pull requests, so a direct push with bypass rights would otherwise skip the checks entirely. It runs `php -l` over all PHP files; flags invalid named arguments to internal PHP functions (catches `sprintf(format:, values:)` → `ArgumentCountError`, which `php -l` does not see); validates the YAML of every `.github` file; asserts every action is pinned to a 40-character commit SHA; and executes `tests/test-*.php` where present.

**Dependabot auto-merge** (`.github/workflows/dependabot-auto-merge.yml`) merges only `semver-patch` and `semver-minor`, and only PRs from `dependabot[bot]` in this repo — never from forks. Major updates get a comment and stay manual. Two repo settings are prerequisites, otherwise this is useless or outright dangerous: "Allow auto-merge" must be enabled, and branch protection must list `CI / Lint & Guards` as a **required status check** — without it `gh pr merge --auto` merges *immediately*, since there is nothing left to wait for. Together with `cooldown: default-days: 7` no action release is adopted during its first week.

Triggered by **pushing a `v*` tag**; the workflow creates the GitHub release automatically. Pipeline: setup PHP/Python/Pandoc/GraphViz → README metadata → slug-named ZIP → SHA256 → upload ZIP + `.sha256` → `plugin_<slug>.json` manifest → PHPDoc → deploy to `gh-pages`.

---

## Abilities API (since 1.1.0)

`includes/abilities.php` registers one read-only ability, `jpkcom-gutenberg-img-alt/list-images-missing-alt`,
in its own `jpkcom-media` category — **not** the `jpkcom-content` category the three content plugins
share, because this reports an editorial gap in the media library rather than published content, and
it is gated differently.

### Why this question and not "which images have no alt text"

The filter overwrites a rendered block's `alt` with the **attachment's** alt text, which makes the
attachment the single source of truth: setting it once fixes every block using that image, with no
post to re-save. What the mechanism cannot do is invent an alt where the attachment has none. That
residual gap is what the ability reports — a question this plugin is uniquely placed to answer.

### The one thing that must not drift

`jpkcom_gutenberg_img_alt_would_inject()` has to give the same answer as the injection itself, and
the injection is THREE gates, not one:

```php
$alt = get_post_meta( ... );                                // unserialises the row
if ( is_string( $alt ) && ! empty( $alt ) ) { ... }        // the filter
if ( '' === trim( $alt ) ) return $unchanged;              // the replacer
```

Three consequences a reasonable-looking check gets wrong:

- **An alt of the single character `0` is empty to PHP**, so nothing is injected. A check for
  `$alt !== ''` calls that image supplied.
- **An alt of only spaces** passes `! empty()` and is then stopped by the trim. A check mirroring
  only the first gate calls that image supplied too.
- **A serialised array or object** (written by code, never by the media modal) is a non-empty raw
  `meta_value`, but `get_post_meta()` returns it unserialised and the `is_string()` gate skips it.
  Before 1.2.0 the filter cast it and injected the literal word `Array`. A check on the raw row calls
  that image supplied; the predicate therefore runs `maybe_unserialize()` first and reports it as
  `not_text`.

Reporting an image as fine when the mechanism has nothing to inject for it is the one answer this
ability must never give, **because it is the answer that stops someone looking.**
`tests/test-abilities.php` executes both the predicate and the real replacer over sixteen awkward values
(raw rows go through a port of core's `is_serialized()`/`maybe_unserialize()` first) and compares them;
mutating the predicate to `$alt !== ''` reddens the `0` and whitespace cases, dropping the unserialise
reddens the five serialised non-text cases.
Verified at runtime for 1.1.0, by running the then `render_block` filter over a real `core/image`
block for each fixture and comparing what it did with what the ability said: zero disagreements.
Not repeated for 1.2.0's `render_block_core/image` hook; the gates the predicate mirrors are unchanged
and the test executes the real replacer.

> Firing `render_block` in a test needs **three** arguments — core's own duotone callback is registered
> there and requires the `WP_Block` instance. Two arguments fatal there and prove nothing.

### The SQL, and why it is SQL

The `not_text` branch mirrors `is_serialized()` (strict) for every type token except `s`. The token
is compared with `ASCII()`, not inside the `REGEXP`: `meta_value` has a case-insensitive collation,
so `'^[bid]'` would also match `I:42;`, which is text to PHP. `REGEXP BINARY` would fix that on
MariaDB but is an error on MySQL 8 (ICU). Checked against MariaDB 11.8 with the PHP rule over 21
values, zero disagreements; not run on MySQL. Known residue: the collation still makes `[0-9.E+-]`
accept a lowercase `e`, so an alt typed as e.g. `d:1e5;` would be listed although it injects.
That errs towards reporting, never towards a false all-clear.

One statement rather than walking the library in PHP: the predicate needs `TRIM()`, which no
`meta_query` comparison expresses, and a page-by-page PHP filter would make `total` a guess. Scope is
`post_type = attachment`, an image MIME type, and status `inherit` or `publish` — trashed and private
uploads are nobody's publishing gap.

### Exposure

Default capability **`upload_files`**, not `read`. The content plugins publish what the site already
shows visitors; this walks the media library, where file names and unattached uploads are editorial
internals. `JPKCOM_GUTENBERG_IMG_ALT_ABILITIES = false` in `wp-config.php` suppresses registration;
`jpkcom_gutenberg_img_alt_ability_capability` and `jpkcom_gutenberg_img_alt_ability_meta` narrow it
further. Measured: anonymous and subscriber denied, author and admin allowed.

> **The Abilities API messages stay English, in every language.** They are read by MCP clients and
> agents, and their wording is the feature. All 33 catalogue entries are untranslated on purpose —
> do not read an empty `msgstr` here as a backlog.

## Security Checklist

- `declare(strict_types=1)` in every PHP file
- Typed `render_block_core/image` callback; attachment id cast to `int`
- Only string meta values are injected (`is_string()`), never a cast array or object
- Alt text set through `WP_HTML_Tag_Processor::set_attribute()`, which escapes it and never
  interprets it — no regular expression touches the markup
- Updater: SHA256 verification + URL validation (audited separately)

---

## Tests

`tests/test-*.php` are plain PHP scripts without WordPress. The replacer needs core's real
`WP_HTML_Tag_Processor`, which `tests/bootstrap-html-api.php` loads from:

- **CI:** `.wp/wp-includes`, a sparse checkout of `WordPress/WordPress` pinned to the commit of the
  7.1.2 tag (`ci.yml`, step "Fetch the WordPress HTML API"). Bump that SHA *and* its comment together.
- **Locally:** `WP_INCLUDES_DIR=/path/to/wordpress/wp-includes php tests/test-alt-replacement.php`.

Without either the bootstrap **fails** the run instead of skipping it. It stubs only what the processor
calls for an `alt` and makes the misuse paths throw (`_doing_it_wrong()`, `esc_url()`). Run against
WordPress 7.0, 7.0.4, 7.1 and 7.1.2: all green. 7.0's `utf8.php` needs `_wp_can_use_pcre_u()` while
loading, so the bootstrap provides it.

---

## Release Checklist

1. Bump version in: header `Version:` + `Stable tag:`, `JPKCOM_GUTENBERG_IMG_ALT_VERSION`, `README.md`, `phpdoc.xml`
2. Add a `### x.y.z` block to `## Changelog` in `README.md`
3. Commit, tag `vx.y.z`, push the tag → the workflow builds and publishes everything
