---
name: rmfa-file-protection
description: Guides work on the Restrict Media File Access file protection pipeline including protecting/unprotecting files, hash-based URL rewriting, file serving with range request support, access control, attachment tracking, and URL replacement in post content. Use when debugging access issues, modifying the protection flow, working with URL rewriting, or changing how files are served.
---

# Skill: RMFA File Protection

## When to Use

Use this skill when:
- Debugging or modifying file protection/unprotection flows.
- Working with hash-based URL rewriting or the rewrite rules.
- Modifying how protected files are served (headers, range requests, access checks).
- Changing access control logic (who can view protected files).
- Working with attachment tracking (which posts use which attachments).
- Debugging URL replacement in post content after protect/unprotect.
- Troubleshooting "file not found" or access-denied issues.

## File Protection Architecture

### Core Concept

When a file is "protected", it is:
1. Physically moved from `wp-content/uploads/YYYY/MM/` to `wp-content/uploads/.protected/YYYY/MM/`.
2. Assigned a SHA-256 hash stored in post meta `_protected_file_hash`.
3. Served via a WordPress rewrite rule at `protected-files/{hash}` instead of a direct file URL.
4. Access-controlled via the `restrict_media_file_access_protect_file` filter (defaults to `! is_user_logged_in()`).

### Protection Flow (Restrict)

```
User clicks "Is restricted file" checkbox
  → AttachmentsAdmin::save_attachment_field()
  → AttachmentsFileManager::set_file_as_protected( $attachment_id )
    1. ensure_file_hash()           — Generate SHA-256 hash if not exists
    2. store_original_paths()       — Save original file + sizes paths to meta
    3. move_files_to_protected_directory() — Move main file + all sizes + scaled originals
    4. update_metadata_for_protected_file() — Update _wp_attachment_metadata
    5. replace_file_urls()           — Update all posts containing this file
  → update_post_meta( '_restricted_file', '1' )
```

### Unprotection Flow (Unrestrict)

```
User unchecks "Is restricted file" checkbox
  → AttachmentsAdmin::save_attachment_field()
  → AttachmentsFileManager::set_file_as_unprotected( $attachment_id )
    1. move_files_back_to_original_location() — Restore from .protected/ to original paths
    2. update_metadata_for_unprotected_file() — Restore _wp_attachment_metadata
    3. cleanup_original_paths()               — Remove _original_file_path, _original_sizes_paths meta
    4. replace_file_urls()                    — Update all posts back to normal URLs
  → delete_post_meta( '_restricted_file' )
```

## Post Meta Keys

| Meta Key | Stored On | Description |
|----------|-----------|-------------|
| `_restricted_file` | attachment | `'1'` if file is restricted. |
| `_protected_file_hash` | attachment | SHA-256 hash used in protected URLs. |
| `_original_file_path` | attachment | Original absolute path before protection. |
| `_original_sizes_paths` | attachment | Array of original size variation paths. |
| `_restricted_file_urls_map` | attachment | Map of old URL → new URL for content replacement. |
| `_rmfa_used_in_posts` | attachment | Array of post IDs that contain this attachment. |
| `_rmfa_attachments` | post | Array of attachment IDs used in this post. |
| `_rmfa_api_restricted` | attachment | Latest REST API restriction activity log. |

## Hash-Based URL System

### URL Format

Protected files are served at:
```
https://example.com/protected-files/{hash}
https://example.com/protected-files/{hash}-{width}x{height}
```

### Hash Generation

```php
$salt      = AUTH_SALT (or fallback to rmfa_hash_salt option)
$file_hash = hash( 'sha256', $salt . basename( $file_path ) . wp_generate_password( 8, false ) )
```

Stored in `_protected_file_hash` post meta. The hash is generated once when a file is first protected and persists across protect/unprotect cycles.

### URL Resolution

1. WordPress rewrite rule: `^protected-files/([^/]+)` → `index.php?protected_file=$matches[1]`.
2. `AttachmentsProtector::handle_protected_file()` picks up the `protected_file` query var on `template_redirect`.
3. Extracts hash and optional size suffix from the URL.
4. Looks up attachment ID via `rmfa_find_attachment_id_by_hash()`.
5. Resolves the physical file path including size variations.

### Attachment URL Modification

`Attachments::modify_attachment_url()` intercepts `wp_get_attachment_url` and returns hash-based URLs for protected files. Similarly modifies:
- `wp_get_attachment_metadata` — Hash-based file paths in metadata.
- `wp_calculate_image_srcset` — Hash-based srcset URLs.
- `attachment_url_to_postid` — Reverse lookup from protected URLs to post IDs.
- `wp_get_attachment_image_attributes` — Adds `rmfa-protected="true"` attribute.

## File Serving Pipeline

### Access Check

```php
$is_protected = apply_filters(
    'restrict_media_file_access_protect_file',
    ! is_user_logged_in(),
    $protected_file
);
```

**Default behavior:** Logged-in users can access protected files; logged-out users cannot.

### Protected Response (Access Denied)

Returns a 1x1 transparent GIF with no-cache headers:
- `Content-Type: image/gif`
- `Cache-Control: no-store, no-cache, must-revalidate, max-age=0`
- Customizable via `restrict_media_file_access_protected_headers` and `restrict_media_file_access_protected_image` filters.

### Unprotected Response (Access Granted)

1. Determines MIME type via `mime_content_type()`.
2. Supports HTTP Range requests (partial content / byte serving) for media streaming.
3. Sets `Accept-Ranges: bytes` header.
4. For range requests: returns `206 Partial Content` with `Content-Range` header.
5. Streams file in 8KB chunks.
6. Fires `restrict_media_file_access_before_serve` action before output.
7. Disables page caching (`DONOTCACHEPAGE`, `batcache_cancel()`).

## Attachment Tracking

`AttachmentsTracking` maintains a bidirectional index between posts and attachments:

### On Post Save (`wp_insert_post`)

1. Extracts all media URLs from post content (href, src, poster, srcset).
2. Resolves each URL to an attachment ID.
3. Computes diff vs previously tracked attachments.
4. Updates `_rmfa_used_in_posts` on each attachment.
5. Updates `_rmfa_attachments` on the post.

### On Post Delete (`before_delete_post`)

Removes the post from all tracked attachments.

### URL Fixing (`wp_insert_post_data`)

Before saving, checks if post content contains wrong URLs (e.g., public URL for a restricted file or protected URL for an unrestricted file) and auto-corrects them using the `_restricted_file_urls_map`.

## Key Filters and Actions

### Filters

| Filter | Description |
|--------|-------------|
| `restrict_media_file_access_protect_file` | Controls access to protected files. Receives `(bool $protect, string $file)`. |
| `restrict_media_file_access_protected_headers` | Modify headers sent for access-denied responses. |
| `restrict_media_file_access_protected_image` | Modify the base64 image data sent for access-denied responses. |
| `rmfa_restricted_file_help_text` | Change the help text on the restriction checkbox. |
| `rmfa_restricted_file_helps_text` | Change the description text below the checkbox. |
| `rmfa_restricted_file_is_disabled` | Disable the restriction toggle for specific attachments. |
| `rmfa_rest_api_restricted_files_access` | Control whether restricted files appear in REST API queries. |
| `rmfa_api_restricted_meta_box_html` | Customize the restriction activity meta box HTML. |

### Actions

| Action | Description |
|--------|-------------|
| `restrict_media_file_access_before_serve` | Fires before serving a protected file. Receives `(int $attachment_id, string $file_path)`. |
| `rmfa_attachment_restrictions_updated_on_save` | Fires after restriction status changes. Receives `(bool $result, int $file_id)`. |

## Jetpack Compatibility

`JetpackCompatibility` skips Jetpack Photon processing for any URL containing `.protected/` or `protected-files/` paths, preventing Photon from attempting to serve protected images.

## Procedure: Debugging File Access Issues

1. Check that rewrite rules are flushed — visit Settings → Permalinks or check `_rmfa_flush_rewrite_rules` transient.
2. Verify the `protected_file` query var is registered — `RewriteRules::add_query_vars()`.
3. Check `_protected_file_hash` exists on the attachment — `rmfa_get_media_protected_file_hash()`.
4. Verify the physical file exists at the expected `.protected/` path — `get_attached_file()`.
5. Check the `restrict_media_file_access_protect_file` filter return value.
6. For 404s on protected URLs: ensure pretty permalinks are enabled.

## Procedure: Adding a New Access Control Rule

1. Hook into `restrict_media_file_access_protect_file` filter.
2. The filter receives `(bool $protect, string $protected_file_hash)`.
3. Return `true` to block access, `false` to allow.
4. Example: allow access based on user role, membership, or token.

```php
add_filter( 'restrict_media_file_access_protect_file', function ( $protect, $protected_file ) {
    if ( current_user_can( 'subscriber' ) ) {
        return false; // Allow subscribers
    }
    return $protect;
}, 10, 2 );
```

## Verification

- `composer lint:php` passes.
- Protected files are physically in `wp-content/uploads/.protected/`.
- Protected URLs resolve correctly at `protected-files/{hash}`.
- Logged-in users can access protected files.
- Logged-out users receive a 1x1 transparent GIF.
- URL replacement works correctly in post content after protect/unprotect.
- Attachment tracking meta is accurate.
