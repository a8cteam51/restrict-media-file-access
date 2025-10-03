# Restrict Media File Access

**Contributors:** wpcomspecialprojects  
**Tags:** media, files, access control, security, attachments, protection  
**Requires at least:** 6.5  
**Tested up to:** 6.5  
**Requires PHP:** 8.3  
**Stable tag:** 1.0.0  
**License:** GPLv3 or later  
**License URI:** <http://www.gnu.org/licenses/gpl-3.0.html>  

Secure your WordPress media files by restricting access to authenticated users only, with custom access control. Protect images, documents, and other uploads with granular access control.

**[Download the latest release](https://github.com/a8cteam51/restrict-media-file-access/releases/latest/download/restrict-media-file-access.zip)**

## Description

Restrict Media File Access is a powerful WordPress plugin that helps you protect your media files from unauthorized access. It provides a secure way to ensure your media files are only accessible to authenticated users, while maintaining the flexibility to control access on a per-file basis.

### Key Features

* **Selective File Protection**: Choose which media files to protect on a per-file basis
* **Secure File Storage**: Protected files are moved to a secure directory outside the public uploads folder
* **Automatic Image Handling**: Supports all image sizes and variations
* **Performance-Optimized**: Built with performance in mind, using WordPress filesystem abstraction
* **Developer Friendly**: Includes filters and actions for customization
* **REST API**: Programmatic access to manage file restrictions

### Requirements

* Pretty permalinks must be used
* The server needs to support dot folders

### Technical Features

* Uses WordPress Filesystem API for secure file operations
* Supports all media file types
* Handles image sizes and thumbnails
* Maintains original file paths for easy restoration
* Implements proper cache control headers
* Secure hash-based file access
* REST API endpoints for programmatic control

### Use Cases

* Membership sites
* Client portals
* Private galleries
* Protected documents
* Premium content
* Educational materials

## Installation

### Manual Installation

1. Download the plugin ZIP file
2. Log in to your WordPress dashboard
3. Navigate to Plugins → Add New → Upload Plugin
4. Upload the ZIP file and click "Install Now"
5. Activate the plugin

## Usage

### Protecting Files

1. Go to Media Library
2. Click on a file to edit
3. Check the "Is restricted file" checkbox
4. Save changes

The file will be moved to a protected location and only be accessible to authenticated users.

### Managing Protected Files

* Protected files are marked with a lock icon in the Media Library
* Original file paths are preserved in metadata for easy restoration
* All image sizes are automatically protected
* Files can be unprotected by unchecking the restriction

### Developer Features

#### Filters

* `restrict_media_file_access_protect_file`: Control whether a file should be protected

### Security Features

* Files are stored in a hidden folder
* Uses WordPress Filesystem API
* Implements proper cache control
* Secure hash-based file access
* No direct file access

## REST API

This plugin provides REST API endpoints for managing file restrictions programmatically. All endpoints require authentication and appropriate capabilities.

### Base URL

All endpoints are prefixed with `/wp-json/restrict-media-file-access/v1/`

### Authentication

All endpoints require WordPress authentication. You can authenticate using:

* WordPress REST API authentication
* Application passwords
* Nonces for logged-in users

### Permissions

* **All endpoints**: Require `upload_files` capability and `edit_post` capability for the specific file ID

### Endpoints

#### 1. Restrict/Unrestrict a File

**Endpoint:** `POST /wp-json/restrict-media-file-access/v1/media/{file_id}/restrict`

**Parameters:**

* `file_id` (required): The ID of the attachment to restrict/unrestrict
* `restrict` (optional): Boolean to restrict (true) or unrestrict (false). Default: true
* `update_post` (optional): Boolean update all posts where the file is located. Default: true

**Permissions:** Requires `upload_files` capability and `edit_post` capability for the file ID

#### 2. Get File Status

**Endpoint:** `GET /wp-json/restrict-media-file-access/v1/media/{file_id}/status`

**Parameters:**

* `file_id` (required): The ID of the attachment

**Permissions:** Requires `upload_files` capability and `edit_post` capability for the file ID

### Security Notes

1. All endpoints require `upload_files` capability and `edit_post` capability for the specific file ID
2. File IDs are validated to ensure they are valid attachments
3. File existence is verified before processing
4. All input is sanitized and validated
5. Error messages do not expose sensitive information

## Frequently Asked Questions

### What happens to protected files when the plugin is deactivated?

Protected files remain in the secure directory but become inaccessible. Upon reactivation, all protected files become accessible again.

### Can I protect files selectively?

Yes, you can choose which files to protect on a per-file basis in the Media Library.

### Does it work with all file types?

Yes, the plugin works with all file types that WordPress allows uploading.

### How does it handle image sizes?

All image sizes (thumbnails, medium, large, etc.) are automatically protected when you protect the original image.

### Can I use the REST API to manage file restrictions?

Yes, the plugin provides REST API endpoints for programmatically managing file restrictions. See the REST API section above for details.

## Contributing

We welcome contributions! Please feel free to submit a Pull Request.
