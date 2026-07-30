# JPKCom Gutenberg Image Block Alt-Attribute

**Plugin Name:** JPKCom Gutenberg Image Block Alt-Attribute  
**Plugin URI:** https://github.com/JPKCom/jpkcom-gutenberg-img-alt  
**Description:** SEO-friendly, dynamic updates for image block alt-attribute texts.  
**Version:** 1.0.9  
**Author:** Jean Pierre Kolb <jpk@jpkc.com>  
**Author URI:** https://www.jpkc.com  
**Contributors:** JPKCom  
**Tags:** Gutenberg, SEO, Image, Block  
**Requires at least:** 6.9  
**Tested up to:** 7.1  
**Requires PHP:** 8.3  
**Stable tag:** 1.0.9  
**License:** GPL-2.0-or-later  
**License URI:** https://www.gnu.org/licenses/gpl-2.0.html

SEO-friendly, dynamic updates for image block alt-attribute texts.


## Description

SEO-friendly, dynamic updates for image block alt-attribute texts.


### Documentation

**API Documentation:** Complete PHPDoc-generated API documentation is available at:
[https://jpkcom.github.io/jpkcom-gutenberg-img-alt/docs/](https://jpkcom.github.io/jpkcom-gutenberg-img-alt/docs/)


## Installation

1. In your admin panel, go to 'Plugins' > and click the 'Add New' button.
2. Click Upload Plugin and 'Choose File', then select the Plugin's .zip file. Click 'Install Now'.
3. Click 'Activate' to use the plugin right away.


## Changelog

### 1.0.9
* CI: the release manifest declared `"network": true` for this plugin. The generator fell back to `true` when the plugin header carries no `Network:` line — which is the case here and is WordPress' own default for *not* network-only, so the fallback was inverted. The manifest now says `false`. This is metadata hygiene rather than a functional fix: WordPress derives network-only status from the plugin header via `is_network_only_plugin()`, not from the update manifest, and the bundled updater already defaulted to `false` on its own. A release is needed for it to take effect because the manifest is only regenerated on a tag push
* CI: the lint and guard workflow now also runs on pushes to `main`. The required status check only ever applied to pull requests, so a direct push with bypass rights skipped every check

### 1.0.8
* Fixed: alt text containing `$1`, `${1}`, `$2` or `\1` corrupted the rendered image tag. Those sequences are backreferences inside a `preg_replace()` replacement string, so ordinary editorial text — a price, a version number — was substituted with part of the matched `<img>` tag instead of being inserted literally. In the `$2` case the tag was closed early and the remainder leaked onto the page as visible text. The replacement now runs through `preg_replace_callback()`, which never interpolates. Thanks to [@yoldaolmak](https://github.com/yoldaolmak) (Kemal Kaya) for finding and fixing this
* Changed: an alt text consisting only of whitespace no longer overwrites the alt attribute already present in the markup
* Added: `tests/test-alt-replacement.php` regression tests for the replacement helper (group references kept literal, attribute escaping, whitespace-only input, images without an alt attribute, multiple images per block)
* Hardening: the new replacement helper is guarded with `function_exists()` so a duplicate plugin copy cannot trigger a redeclare fatal

### 1.0.7
* Changed: `Tested up to` raised to WordPress 7.1
* Changed: the bundled updater's runtime floor now matches the plugin's own minimum. It bailed out below WordPress 6.8 while the plugin header has required 6.9 for several releases, so the check could never fire on a supported installation
* CI: the release manifest's fallback values for `requires` and `tested` now say 6.9 and 7.1. They only apply when the README metadata cannot be read, but a stale fallback would have published a minimum the plugin no longer supports

### 1.0.6
* Added: plugin banners (`assets/banner-1544x500.avif`, `assets/banner-772x250.avif`) — a plain `#3c4955` surface with no lettering. The update manifest already advertised these two URLs, but nothing was published under them, so the plugin card in wp-admin had a broken banner

### 1.0.5
* CI: the release step no longer copies the staging directory into itself, so the ZIP has no empty `jpkcom-gutenberg-img-alt/jpkcom-gutenberg-img-alt/` folder
* CI: bumped the pinned GitHub Actions (checkout v7.0.1, setup-python v7.0.0, action-gh-release v3.0.2, fetch-metadata v3.1.0), still pinned to full commit SHAs
* CI: the release ZIP now excludes the development-only `tests/` and `tools/` directories
* CI: security and regression tests now run on every pull request, where a plugin has them

### 1.0.4
* Security: update packages are now verified *before* installation — the verified file is handed to WordPress instead of being downloaded a second time, so the bytes that were checked are the bytes that get installed
* Security: a missing or unfetchable SHA-256 checksum now aborts the update instead of installing unverified code (previously it silently skipped verification)
* Security: pinned every GitHub Action to a full commit SHA and added Dependabot with a 7-day cooldown, so a moved tag can no longer change the release build
* Security: tightened which download the updater claims, so sibling plugins cannot match each other's package
* Fixed: `sprintf()` calls in the updater bound named arguments to a variadic parameter, which raises `ArgumentCountError` on PHP 8.3
* Fixed: the "View Details" modal could fail with a `TypeError` when the manifest omitted `requires_plugins`
* Performance: a failed manifest fetch is now cached for an hour instead of being retried on every admin request
* Added: CI workflow on every pull request (PHP lint, named-argument check, YAML validation, action-pinning guard)

### 1.0.3
* Docs: linked the published PHPDoc API documentation

### 1.0.2
* Added secure self-hosted plugin updates via GitHub with SHA256 checksum verification
* Added an automated release workflow (builds the ZIP, generates the manifest and deploys to gh-pages on tag push)
* Raised the minimum WordPress version to 6.9 and "Tested up to" to WordPress 7.0
* Switched license metadata to the SPDX identifier `GPL-2.0-or-later` with the HTTPS license URI
* Added PHPDoc-generated API documentation, built and deployed to gh-pages on release
* Hardening: enabled `declare(strict_types=1)` and added precise closure parameter/return types

### 1.0.1
* Replacement bugfix

### 1.0.0
* Initial Release
