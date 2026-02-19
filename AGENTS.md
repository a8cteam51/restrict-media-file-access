# Restrict Media File Access — Agent Instructions

This file provides AI coding assistants with the context they need to work effectively on the **Restrict Media File Access** WordPress plugin.

## Plugin Overview

**Restrict Media File Access** (`restrict-media-file-access`) is a WordPress plugin that protects media files from unauthorized access. It provides:

- Per-file protection via a checkbox in the Media Library.
- Secure file storage by moving protected files to a hidden `.protected` directory.
- Hash-based URL rewriting so protected file paths are never exposed.
- Access control via the `restrict_media_file_access_protect_file` filter (default: logged-in users can access).
- HTTP Range request support for media streaming.
- Automatic URL replacement in post content when files are protected/unprotected.
- Bidirectional attachment tracking (which posts use which attachments).
- A REST API under the `restrict-media-file-access/v1` namespace.
- Jetpack Photon compatibility.
- GitHub-hosted self-update mechanism.
- Admin UI: restriction checkbox on attachments, "Restricted" column in Media Library, activity meta box.

**Text domain:** `restrict-media-file-access`
**Namespace:** `A8C\SpecialProjects\RestrictMediaFileAccess\`
**Requires:** WordPress 6.4+, PHP 8.3+

---

## Directory Structure

```
restrict-media-file-access/
├── restrict-media-file-access.php  ← Bootstrap (constants, autoloader, activation hooks)
├── functions-bootstrap.php         ← Plugin metadata, version checks, error display
├── functions.php                   ← Plugin instance getter, includes loader, logger
├── AGENTS.md                       ← You are here
├── CLAUDE.md                       ← Points to this file
├── .agents/                        ← Project-specific agent skills
│   └── skills/
│       ├── rmfa-architecture/SKILL.md
│       ├── rmfa-file-protection/SKILL.md
│       └── rmfa-testing/SKILL.md
│
├── src/                            ← PSR-4 autoloaded PHP (A8C\SpecialProjects\RestrictMediaFileAccess\)
│   ├── Plugin.php                  ← Singleton main class; registers all services
│   ├── Attachments.php             ← URL modification, metadata, srcset, image attributes
│   ├── AttachmentsAdmin.php        ← Admin UI: restriction checkbox, media column, meta box
│   ├── AttachmentsFileManager.php  ← Core: protect/unprotect files, move, URL replacement
│   ├── AttachmentsProtector.php    ← File serving: access check, headers, range requests
│   ├── AttachmentsRemoval.php      ← Cleanup on attachment deletion
│   ├── AttachmentsTracking.php     ← Bidirectional post↔attachment tracking
│   ├── AttachmentsUpload.php       ← Unique filename check against protected directory
│   ├── BulkOperations.php          ← Initial setup: batch index all posts
│   ├── CacheCleanup.php            ← Cache invalidation on attachment/post deletion
│   ├── Cron.php                    ← Initial setup cron job
│   ├── Filesystem.php              ← Static WP_Filesystem wrapper
│   ├── JetpackCompatibility.php    ← Skip Photon for protected images
│   ├── RestApi.php                 ← REST API endpoints (restrict, status)
│   ├── RewriteRules.php            ← WordPress rewrite rules for protected-files/{hash}
│   ├── SelfUpdate.php              ← GitHub-hosted auto-updates
│   └── Settings.php                ← Media Settings page section
│
├── includes/                       ← Procedural PHP files loaded by functions.php
│   ├── constants.php               ← RESTRICT_MEDIA_FILE_ACCESS_PROTECTED_DIR, _PROTECTED_PATH
│   ├── functions.php               ← Global helper functions (rmfa_*)
│   ├── assets.php                  ← Asset meta helper
│   └── settings.php                ← (empty placeholder)
│
├── assets/                         ← Admin CSS and JS
│   └── admin/
│       ├── css/
│       │   ├── media.css                   ← General admin media styles
│       │   └── media-restricted-files.css  ← Restricted file border styles
│       └── js/
│           └── media.js                    ← Media library JS enhancements
│
├── models/                         ← Model classes (classmap autoloaded, currently empty)
├── languages/                      ← i18n files
│
├── tests/
│   ├── Integration/                ← Codeception integration tests
│   ├── EndToEnd/                   ← Codeception E2E tests
│   ├── Support/                    ← Test support classes and data
│   ├── Integration.suite.yml       ← Integration suite config
│   ├── EndToEnd.suite.yml          ← E2E suite config
│   └── README.md                   ← Local test setup instructions
│
├── composer.json                   ← PHP deps + scripts (lint, test, i18n)
├── package.json                    ← JS deps + scripts (wp-env, tests)
├── codeception.dist.yml            ← Codeception configuration
├── .phpcs.xml                      ← PHPCS configuration
├── .phpmd.xml                      ← PHPMD configuration
├── .phpstan.neon                   ← PHPStan configuration
└── .wp-env.json                    ← wp-env configuration
```

---

## Architecture

### Bootstrap

`restrict-media-file-access.php` defines constants, loads `functions-bootstrap.php`, loads translations, declares WC compatibility, validates requirements, and hooks `plugins_loaded` → `Plugin::get_instance()->maybe_initialize()`. Activation creates the `.protected` directory, schedules initial tracking setup, and sets a transient to flush rewrite rules. See `.agents/skills/rmfa-architecture/SKILL.md` for the full bootstrap sequence.

### Service Pattern

`Plugin` is a singleton. `Plugin::initialize()` instantiates all services and calls `initialize()` on each. See `.agents/skills/rmfa-architecture/SKILL.md` for the service pattern, code examples, and how to add new services.

Registered services (in order):

| Key | Class |
|-----|-------|
| `self-update` | `SelfUpdate` |
| `cron` | `Cron` |
| `settings` | `Settings` |
| `attachments-protector` | `AttachmentsProtector` |
| `jetpack-compatibility` | `JetpackCompatibility` |
| `attachments` | `Attachments` |
| `attachments-admin` | `AttachmentsAdmin` |
| `attachments-upload` | `AttachmentsUpload` |
| `attachments-removal` | `AttachmentsRemoval` |
| `rewrite-rules` | `RewriteRules` |
| `cache-cleanup` | `CacheCleanup` |
| `attachments-tracking` | `AttachmentsTracking` |
| `rest-api` | `RestApi` |

### REST API Routes

All routes registered under `restrict-media-file-access/v1`:

- `POST /media/{file_id}/restrict` — Restrict or unrestrict a file.
- `GET /media/{file_id}/status` — Get file restriction status and metadata.

### File Protection

Files are physically moved to `.protected/` and served via hash-based rewrite rules. `AttachmentsProtector` handles `template_redirect` to serve or block access. `AttachmentsFileManager` handles the protect/unprotect lifecycle. See `.agents/skills/rmfa-file-protection/SKILL.md` for the full pipeline.

### Access Control

Default: `! is_user_logged_in()` — logged-in users can access, logged-out users see a 1x1 transparent GIF. Customizable via the `restrict_media_file_access_protect_file` filter. See `.agents/skills/rmfa-file-protection/SKILL.md` for details.

### Attachment Tracking

`AttachmentsTracking` maintains a bidirectional index of which posts contain which attachments, using `_rmfa_used_in_posts` (on attachments) and `_rmfa_attachments` (on posts). `BulkOperations` builds this index on first activation. See `.agents/skills/rmfa-file-protection/SKILL.md` for details.

---

## Coding Standards

### PHP

- **WordPress PHP Coding Standards** — enforced via PHPCS (`.phpcs.xml`) extending `a8cteam51/team51-configs`.
- MUST indent with **tabs**.
- MUST use `array()` long syntax (not `[]`).
- MUST use Yoda conditions: `if ( 'value' === $var )`.
- MUST use strict comparisons (`===`, `!==`).
- MUST declare visibility (`public`, `protected`, `private`) on all class members.
- MUST use full `<?php` tags; no closing `?>` in pure PHP files.
- Uses `declare(strict_types=1)` in most source files (follows existing convention).
- CRITICAL: `$wpdb->prepare()` for ALL database queries — no exceptions.
- CRITICAL: Escape all output — `esc_html()`, `esc_attr()`, `esc_url()`.
- CRITICAL: Sanitize all input — `sanitize_text_field()`, `sanitize_email()`.
- Global function prefixes: `rmfa_` or `restrict_media_file_access_`.
- PHPCS prefixes: `a8csp_`, `rmfa_`, `restrict_media_file_access_`, `A8C\SpecialProjects\RestrictMediaFileAccess`.

### JavaScript

- **WordPress JavaScript Coding Standards**.
- MUST indent with **tabs**.
- MUST use single quotes for strings.
- MUST use strict equality (`===`).

### Accessibility

- WCAG 2.2 Level AA minimum.
- Semantic HTML, ARIA labels, keyboard navigation, 4.5:1 contrast ratio.

---

## Git Conventions

- Branch naming: `feature/<description>`, `fix/<description>`, `chore/<description>`.
- Commit messages: imperative mood ("Add feature" not "Added feature"), reference issues where applicable.
- PRs: include description of changes, testing steps, and screenshots for UI changes.
- All PRs MUST pass `composer lint:php` before merge.

---

## Development Workflows

```bash
# Install dependencies
composer run-script packages-install
npm install

# Lint
composer lint:php          # PHPCS + PHPMD + PHPStan
composer lint:php:phpcs    # PHPCS only
composer lint:php:phpmd    # PHPMD only
composer lint:php:phpstan  # PHPStan only
composer format:php        # PHPCS auto-fix

# Tests (requires wp-env running)
npm run wp-env:start
npm run tests:run                     # Integration + E2E
npm run tests:run:integration         # Integration only
npm run tests:run:end-to-end          # E2E only

# i18n
composer internationalize             # Make POT, update PO, make MO, make PHP
```

---

## Testing

### Integration Tests (Codeception + wp-browser)

- **Location:** `tests/Integration/`
- **Framework:** Codeception with `lucatume/wp-browser` ^4.
- **Namespace:** `Tests\Integration\*`
- Run: `composer tests:run:integration`

### Codeception E2E

- **Location:** `tests/EndToEnd/`
- **Requires:** Selenium with Chromium.
- Run: `composer tests:run:end-to-end`

See `.agents/skills/rmfa-testing/SKILL.md` for full details on writing tests, test patterns, naming conventions, and local setup.

---

## Skills

This plugin uses a combination of [WordPress/agent-skills](https://github.com/WordPress/agent-skills) and plugin-specific skills.

### Plugin-Specific Skills (in `.agents/skills/`)

| Skill | When to Use |
|-------|-------------|
| `rmfa-architecture` | Working on plugin PHP: services, REST API, cron, settings. |
| `rmfa-file-protection` | File protection pipeline, URL rewriting, access control, file serving. |
| `rmfa-testing` | Writing or running tests (Codeception). |

### Recommended: WordPress Agent-Skills

The following skills from [WordPress/agent-skills](https://github.com/WordPress/agent-skills) are recommended but **not bundled** — install them globally to keep them up-to-date. See `.agents/README.md` for installation instructions.

| Skill | When to Use |
|-------|-------------|
| `wordpress-router` | Classifying the repo and routing to the correct workflow. |
| `wp-project-triage` | Inspecting project type, tooling, and versions. |
| `wp-plugin-development` | General WordPress plugin architecture and hooks. |
| `wp-rest-api` | REST API routes, schema, auth, permission callbacks. |
| `wp-performance` | Profiling, caching, database optimization. |
| `wp-wpcli-and-ops` | WP-CLI operations and automation. |
| `wp-playground` | Local development with wp-env. |
| `wp-phpstan` | PHPStan static analysis setup. |

---

## Important Notes & Common Pitfalls

### Important Notes

- **Pretty permalinks required:** The rewrite rules for `protected-files/{hash}` require pretty permalinks to be enabled.
- **Dot folder support:** The server must support folders starting with `.` (e.g., `.protected`).
- **Singleton pattern:** `Plugin` is a singleton. In tests, reset via reflection.
- **WooCommerce compatibility:** The plugin declares HPOS compatibility but does not depend on WooCommerce.
- **Protected directory:** Created at `wp-content/uploads/.protected/` on activation. Mirrors the year/month subdirectory structure of the regular uploads directory.
- **File hashes persist:** The `_protected_file_hash` post meta persists even after unprotecting, so re-protecting reuses the same hash.
- **Self-update:** The plugin auto-updates from GitHub releases via the `update_plugins_github.com` filter.
- **Dual-layer caching:** `rmfa_set_cache()`/`rmfa_get_cache()` use both WP Object Cache and transients for performance.

### Common Pitfalls

- **DO NOT** use short array syntax (`[]`) — this project uses `array()` long syntax per WordPress standards.
- **DO NOT** edit files in `vendor/` — these are Composer-managed dependencies.
- **DO NOT** forget `$wpdb->prepare()` for ALL database queries — no exceptions.
- **DO NOT** create REST routes without a `permission_callback`.
- **DO NOT** bypass the `Filesystem` helper for file operations — use it instead of raw PHP file functions.
- **DO NOT** forget to flush rewrite rules after changing the rewrite rule pattern.
- **DO NOT** assume direct file access is possible for protected files — they are served through WordPress.
- **DO NOT** modify `_protected_file_hash` directly — use `AttachmentsFileManager` methods.
- Singletons (`Plugin`) **MUST** be reset via reflection in tests.
- Global state (`$_GET`, `$_SERVER`) **MUST** be saved in `setUp()` and restored in `tearDown()` in tests.
