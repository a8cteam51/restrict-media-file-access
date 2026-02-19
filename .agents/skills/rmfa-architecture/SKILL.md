---
name: rmfa-architecture
description: Guides work on Restrict Media File Access WordPress plugin PHP architecture including services, REST API, cron, settings, filesystem helper, and bulk operations. Use when adding or modifying services, REST endpoints, admin UI, settings, cron jobs, or understanding the plugin bootstrap and service pattern.
---

# Skill: RMFA Plugin Architecture

## When to Use

Use this skill when:
- Adding or modifying services, REST endpoints, or settings in the RMFA plugin.
- Understanding the plugin bootstrap and service registration pattern.
- Working with the filesystem helper, bulk operations, or cron jobs.
- Adding new features that need to integrate with existing services.

## Bootstrap Flow

1. `restrict-media-file-access.php` defines constants (`RESTRICT_MEDIA_FILE_ACCESS_BASENAME`, `_DIR_PATH`, `_DIR_URL`).
2. Requires `functions-bootstrap.php` (plugin metadata, version checks, error display).
3. Loads translations via `init` hook.
4. Declares WooCommerce compatibility (HPOS).
5. Loads Composer autoloader from `vendor/autoload.php`.
6. Validates requirements (PHP 8.3+, WP 6.4+) via `restrict_media_file_access_validate_requirements()`.
7. If requirements pass: requires `functions.php` (plugin instance getter, includes loader, logger) and hooks `plugins_loaded` → `Plugin::get_instance()->maybe_initialize()`.
8. Activation hook: creates `.protected` directory, schedules initial setup cron, sets transient to flush rewrite rules.

## Service Pattern

Every feature is a "service" — a class with an `initialize()` method that hooks into WordPress. `Plugin::initialize()` instantiates all services and calls `initialize()` on each.

```php
// Adding a new service:
// 1. Create the class in src/
class MyFeature {
    public function initialize(): void {
        add_action( 'init', array( $this, 'do_something' ) );
    }

    public function do_something(): void {
        // Feature logic
    }
}

// 2. Register it in Plugin::initialize()
$this->services = array(
    // ... existing services ...
    'my-feature' => new MyFeature(),
);
```

### Registered Services (in order)

| Key | Class | Responsibility |
|-----|-------|----------------|
| `self-update` | `SelfUpdate` | GitHub-hosted auto-updates via `update_plugins_github.com` filter. |
| `cron` | `Cron` | Initial setup cron job (`rmfa_run_initial_setup`). |
| `settings` | `Settings` | Admin settings on Media Settings page, asset enqueuing. |
| `attachments-protector` | `AttachmentsProtector` | Handles `template_redirect` to serve/block protected files. |
| `jetpack-compatibility` | `JetpackCompatibility` | Skips Jetpack Photon for restricted images. |
| `attachments` | `Attachments` | Modifies attachment URLs, metadata, srcset, image attributes. |
| `attachments-admin` | `AttachmentsAdmin` | Admin UI: restriction checkbox, media column, meta box. |
| `attachments-upload` | `AttachmentsUpload` | Unique filename check against protected directory. |
| `attachments-removal` | `AttachmentsRemoval` | Cleanup on attachment deletion. |
| `rewrite-rules` | `RewriteRules` | WordPress rewrite rules for `protected-files/{hash}` URLs. |
| `cache-cleanup` | `CacheCleanup` | Cache invalidation on attachment/post deletion. |
| `attachments-tracking` | `AttachmentsTracking` | Bidirectional tracking: which posts use which attachments. |
| `rest-api` | `RestApi` | REST API endpoints for restrict/unrestrict/status. |

## REST API

All routes registered under `restrict-media-file-access/v1`:

| Method | Endpoint | Handler | Description |
|--------|----------|---------|-------------|
| POST/PUT/PATCH | `/media/{file_id}/restrict` | `RestApi::restrict_file()` | Restrict or unrestrict a file. |
| GET | `/media/{file_id}/status` | `RestApi::get_file_status()` | Get restriction status, URL, metadata. |

### Permission Callback

All endpoints require `upload_files` capability AND `edit_post` for the specific file ID.

### Request Parameters (restrict endpoint)

| Param | Type | Default | Description |
|-------|------|---------|-------------|
| `file_id` | int | required | Attachment post ID. |
| `restrict` | bool | `true` | Whether to restrict or unrestrict. |
| `update_post` | bool | `true` | Whether to update all posts containing the file. |

### REST Filters

- `rmfa_rest_api_restricted_files_access` — Controls whether restricted files appear in REST API attachment queries.
- `rmfa_filter_attachment_query` — Modifies the attachment query args after RMFA filtering.

## Settings

Settings live on the WordPress **Media Settings** page (`/wp-admin/options-media.php`) in a custom section:

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `rmfa_show_border_on_restricted_files` | bool | `true` | Show visual border on restricted files in admin. |
| `rmfa_should_old_restricted_file_urls_work_if_file_set_as_public` | bool | `true` | Allow old protected URLs to resolve after unrestricting. |

## Filesystem Helper

`Filesystem` is a static utility wrapping `WP_Filesystem_Base`:

| Method | Description |
|--------|-------------|
| `Filesystem::exists( $path )` | Check if file/dir exists. |
| `Filesystem::move( $src, $dst )` | Move a file. |
| `Filesystem::delete( $path )` | Delete a file. |
| `Filesystem::delete_recursive( $path )` | Delete a directory recursively. |
| `Filesystem::read( $path )` | Read file contents. |
| `Filesystem::dirlist( $path, $options )` | List directory contents. |

## Bulk Operations

`BulkOperations::update_all_attachments()` processes all public posts in batches to build the attachment tracking index. Called by `Cron::run_initial_setup()` on first activation.

## Constants

| Constant | Value | Description |
|----------|-------|-------------|
| `RESTRICT_MEDIA_FILE_ACCESS_PROTECTED_DIR` | `.protected` | Directory name inside `wp-content/uploads/`. |
| `RESTRICT_MEDIA_FILE_ACCESS_PROTECTED_PATH` | `protected-files` | URL path segment for rewrite rules. |
| `RESTRICT_MEDIA_FILE_ACCESS_BASENAME` | `restrict-media-file-access/restrict-media-file-access.php` | Plugin basename. |
| `RESTRICT_MEDIA_FILE_ACCESS_DIR_PATH` | (dynamic) | Plugin directory path. |
| `RESTRICT_MEDIA_FILE_ACCESS_DIR_URL` | (dynamic) | Plugin directory URL. |

## Global Helper Functions

| Function | Description |
|----------|-------------|
| `rmfa_set_file_as_protected( $id, $options )` | Protect a file (delegates to `AttachmentsFileManager`). |
| `rmfa_set_file_as_unprotected( $id, $options )` | Unprotect a file. |
| `rmfa_is_media_restricted( $id )` | Check if an attachment is restricted. |
| `rmfa_find_attachment_id_by_hash( $hash )` | Lookup attachment ID from file hash. |
| `rmfa_get_media_protected_file_hash( $id )` | Get the hash for a protected file. |
| `rmfa_get_posts_with_media_url( $id )` | Get post IDs that contain a given attachment. |
| `rmfa_set_cache()` / `rmfa_get_cache()` / `rmfa_delete_cache()` | Dual-layer cache (WP Object Cache + transients). |
| `rmfa_log_error( $message )` | Log with `RMFA:` prefix. |

## Verification

- `composer lint:php` passes.
- `npm run tests:run` passes.
- New services are registered in `Plugin::initialize()`.
- New REST routes have proper `permission_callback`.
