---
name: rmfa-testing
description: Guides writing and running tests for the Restrict Media File Access WordPress plugin using Codeception/wp-browser integration tests and Codeception E2E tests. Use when writing tests, debugging test failures, or adding coverage for new features.
---

# Skill: RMFA Testing

## When to Use

Use this skill when:
- Writing or modifying tests for the RMFA plugin.
- Running the test suite (integration or E2E Codeception).
- Debugging test failures.
- Adding test coverage for new features.

## Test Frameworks

| Framework | Location | Purpose |
|-----------|----------|---------|
| Codeception + wp-browser | `tests/Integration/` | WordPress integration tests (DB, hooks, filters). |
| Codeception + wp-browser | `tests/EndToEnd/` | Codeception browser E2E tests. |

### Dependencies

- **PHP:** `lucatume/wp-browser` ^4.
- **JS:** `@wordpress/env` ^10.8.
- **Infrastructure:** Docker (for wp-env), Selenium with Chromium (for E2E).

## Running Tests

All commands run from the `restrict-media-file-access/` plugin root:

```bash
# Start wp-env (required for all tests)
npm run wp-env:start

# Integration tests
composer tests:run:integration

# E2E tests (Codeception + Selenium)
composer tests:run:end-to-end

# All Codeception tests (integration + E2E)
npm run tests:run

# Via wp-env (runs inside container)
npm run tests:run:integration
npm run tests:run:end-to-end

# Clean Codeception output
composer tests:clean
```

## Local Setup (macOS)

1. Install Docker, Composer, Node.js.
2. Enable host networking in Docker settings (`Resources > Network`).
3. Start Selenium: `docker run -d --shm-size="2g" --net=host --name="selenium-chromium" selenium/standalone-chromium:latest`
4. Install dependencies: `composer run-script packages-install && npm install`
5. Copy `tests/.dist.env` to `tests/.env`.
6. Create database fixture: `npm run tests:export-db`
7. Run tests: `npm run tests:run`

## Test Configuration

### Codeception Config (`codeception.dist.yml`)

```yaml
namespace: Tests
support_namespace: Support
paths:
    tests: tests
    output: tests/_output
    data: tests/Support/Data
    support: tests/Support
params:
    - tests/.env
```

### Suite Configs

- `tests/Integration.suite.yml` — Integration suite configuration.
- `tests/EndToEnd.suite.yml` — E2E suite configuration.

### Environment Variables (`tests/.env`)

Copied from `tests/.dist.env`. Contains database credentials and WordPress test config for wp-browser.

## Writing Integration Tests

### File and Namespace Convention

```
tests/Integration/<Name>Test.php
→ Tests\Integration\<Name>Test
```

Or with subdirectories:

```
tests/Integration/<SubDir>/<Name>Test.php
→ Tests\Integration\<SubDir>\<Name>Test
```

### Test Class Structure

```php
<?php

namespace Tests\Integration;

use WP_UnitTestCase;
use A8C\SpecialProjects\RestrictMediaFileAccess\AttachmentsFileManager;

class AttachmentsFileManagerTest extends WP_UnitTestCase {

    private AttachmentsFileManager $file_manager;

    public function setUp(): void {
        parent::setUp();
        $this->file_manager = new AttachmentsFileManager();
    }

    public function tearDown(): void {
        parent::tearDown();
    }

    public function test_set_file_as_protected_returns_true_for_new_restriction(): void {
        $attachment_id = self::factory()->attachment->create_upload_object( ... );
        $result = $this->file_manager->set_file_as_protected( $attachment_id );
        $this->assertTrue( $result );
    }

    public function test_set_file_as_protected_returns_false_when_already_protected(): void {
        $attachment_id = self::factory()->attachment->create_upload_object( ... );
        $this->file_manager->set_file_as_protected( $attachment_id );
        $result = $this->file_manager->set_file_as_protected( $attachment_id );
        $this->assertFalse( $result );
    }
}
```

### Key Testing Patterns

**Reset singleton between tests:**

`Plugin` is a singleton. Reset via reflection when needed:

```php
$reflection = new \ReflectionClass( Plugin::class );
$property = $reflection->getProperty( 'instance' );
$property->setAccessible( true );
$property->setValue( null, null );
```

**Test file operations with the WordPress attachment factory:**

```php
$attachment_id = self::factory()->attachment->create_upload_object(
    '/path/to/test/image.jpg',
    0 // parent post ID
);
```

**Verify post meta:**

```php
$this->assertSame( '1', get_post_meta( $attachment_id, '_restricted_file', true ) );
$this->assertNotEmpty( get_post_meta( $attachment_id, '_protected_file_hash', true ) );
```

**Test URL modifications:**

```php
$url = wp_get_attachment_url( $attachment_id );
$this->assertStringContains( 'protected-files/', $url );
```

**Save/restore globals:**

Session and file-serving code may read `$_GET`, `$_SERVER`. Save and restore in setUp/tearDown:

```php
private array $original_server;

public function setUp(): void {
    parent::setUp();
    $this->original_server = $_SERVER;
}

public function tearDown(): void {
    $_SERVER = $this->original_server;
    parent::tearDown();
}
```

**No placeholder assertions:**

Every test method must have a meaningful assertion. Do not use `$this->assertTrue( true )` as a placeholder.

### Test Naming Convention

```
test_<action>_<condition>_<expected_result>
```

Examples:
- `test_set_file_as_protected_returns_true_for_new_file`
- `test_set_file_as_unprotected_returns_false_when_not_restricted`
- `test_modify_attachment_url_returns_hash_url_for_protected_file`
- `test_handle_protected_file_returns_gif_for_logged_out_user`
- `test_restrict_file_endpoint_returns_403_when_disabled`

## Procedure: Adding Tests for a New Feature

1. Determine if the feature needs integration tests, E2E tests, or both.
2. **Integration:** Create `tests/Integration/<Name>Test.php`. Test the PHP logic in isolation (file operations, meta, hooks, filters, REST endpoints).
3. **E2E (Codeception):** Create `tests/EndToEnd/<Name>Cest.php`. Test user-facing behavior in the browser (media library, restriction toggle, file access).
4. Follow the naming and namespace conventions above.
5. Run the full suite to verify no regressions: `npm run tests:run`.

## Verification

- All tests pass: `composer tests:run:integration`.
- No placeholder assertions (`assertTrue( true )`).
- New code has corresponding test coverage.
- Tests are isolated — no test depends on another test's side effects.
- Global state (`$_GET`, `$_SERVER`) is saved/restored.
- Singletons are properly reset between tests.
