# JPKCom Gutenberg Image Block Alt-Attribute – Developer Reference

## Plugin Overview

SEO helper that keeps the `alt` attribute of rendered `core/image` blocks in sync with the attachment's stored alt text. On every front-end render it reads `_wp_attachment_image_alt` for the image's attachment ID and rewrites the `alt="…"` in the block HTML.

- **Text Domain:** none declared (defaults to slug `jpkcom-gutenberg-img-alt`)
- **Min PHP:** 8.3 | **Min WP:** 6.9
- **Network:** not network-only (no `Network:` header)

---

## Architecture

```
Main file (jpkcom-gutenberg-img-alt.php)
├── declare(strict_types=1)
├── Plugin header
├── JPKCOM_GUTENBERG_IMG_ALT_VERSION constant
├── init @ priority 5: boot JPKComGitPluginUpdater
└── render_block filter (priority 10, 2 args):
    core/image + attrs.id → get_post_meta(_wp_attachment_image_alt)
    → preg_replace the alt="" in the rendered HTML (esc_attr the value)
```

The callback is typed `( string $block_content, array $block ): string`. The attachment id is cast to `int`, `blockName` is read null-safe, and `preg_replace()` falls back to the original content if it returns `null`.

---

## Constants

| Constant | Value | Purpose |
|----------|-------|---------|
| `JPKCOM_GUTENBERG_IMG_ALT_VERSION` | `'1.0.2'` | Plugin version (sync with header/README/phpdoc.xml) |

---

## File Structure

```
jpkcom-gutenberg-img-alt/
├── jpkcom-gutenberg-img-alt.php  ← Main: header, constant, render_block filter, updater bootstrap
├── includes/
│   └── class-plugin-updater.php  ← GitHub auto-updater (namespace: JPKComGutenbergImgAltGitUpdate)
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
- Shared JPKCom updater (downstream copy of the upstream `jpkcom-post-filter` updater; do not edit per-plugin). SHA256 verification, `wp_safe_remote_get()`, URL validation, race-condition lock, 24 h cache, timing-safe `hash_equals()`.
- Hooks: `plugins_api`, `site_transient_update_plugins`, `upgrader_process_complete`, `upgrader_pre_download`.

---

## Release Workflow

Triggered by **pushing a `v*` tag**; the workflow creates the GitHub release automatically. Pipeline: setup PHP/Python/Pandoc/GraphViz → README metadata → slug-named ZIP → SHA256 → upload ZIP + `.sha256` → `plugin_<slug>.json` manifest → PHPDoc → deploy to `gh-pages`.

---

## Security Checklist

- `declare(strict_types=1)` in every PHP file
- Typed `render_block` callback; attachment id cast to `int`
- Alt text escaped with `esc_attr()` before injection
- Updater: SHA256 verification + URL validation (audited separately)

---

## Release Checklist

1. Bump version in: header `Version:` + `Stable tag:`, `JPKCOM_GUTENBERG_IMG_ALT_VERSION`, `README.md`, `phpdoc.xml`
2. Add a `### x.y.z` block to `## Changelog` in `README.md`
3. Commit, tag `vx.y.z`, push the tag → the workflow builds and publishes everything
