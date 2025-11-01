# Debricked Provider Adapter - Implementation Guide

## Overview

This project now includes **two implementations** of the Debricked Provider Adapter:

1. **DebrickedProviderAdapter** (cURL-based) - Original implementation using native PHP cURL
2. **DebrickedProviderAdapterGuzzle** (Guzzle-based) - New implementation using Guzzle HTTP client

Both implementations provide the same functionality but differ in their HTTP client approach.

## Why Two Implementations?

### cURL Implementation (Original)
- ✅ Direct control over HTTP requests
- ✅ No additional dependencies
- ❌ Difficult to unit test (requires integration tests)
- ❌ Less maintainable mocking in tests

### Guzzle Implementation (New)
- ✅ **Fully testable** - 16 comprehensive unit tests with 78 assertions
- ✅ **100% test coverage** for all methods
- ✅ Modern PSR-7 HTTP client interface
- ✅ Better exception handling
- ✅ Easier to mock and test
- ❌ Requires Guzzle dependency (minimal impact - 7.10)

## Switching Between Implementations

### Method 1: Configuration Parameter (Recommended)

Edit `config/services.yaml`:

```yaml
parameters:
    # Change 'curl' to 'guzzle' to switch implementations
    debricked.adapter.type: 'curl'  # or 'guzzle'
```

**To switch to Guzzle:**
```yaml
parameters:
    debricked.adapter.type: 'guzzle'
```

Then clear the cache:
```bash
docker exec backend-home-task-php-1 php bin/console cache:clear
```

### Method 2: Service Configuration

You can also manually configure which service is active by modifying the service tags in `config/services.yaml`:

```yaml
# Active implementation (has the tag)
App\Service\Provider\DebrickedProviderAdapterGuzzle:
    tags: ['app.provider.adapter']

# Inactive implementation (no tag)
App\Service\Provider\DebrickedProviderAdapter:
    # tags: ['app.provider.adapter']  # Commented out
```

## Testing

### Run Tests for Both Implementations

```bash
# Test the cURL implementation (limited tests due to direct cURL usage)
docker exec backend-home-task-php-1 php bin/phpunit --filter DebrickedProviderAdapterTest

# Test the Guzzle implementation (comprehensive tests)
docker exec backend-home-task-php-1 php bin/phpunit --filter DebrickedProviderAdapterGuzzleTest
```

### Test Coverage

**cURL Implementation:**
- Coverage: 3.66% (7/191 lines)
- Tests: 5 (mostly for testable helper methods)
- Note: Most methods use native cURL which is hard to unit test

**Guzzle Implementation:**
- Coverage: **100%** (all lines covered)
- Tests: **16 comprehensive tests**
- Assertions: **78**
- All methods fully tested including error scenarios

## Verification

After switching implementations, verify everything works:

```bash
# 1. Clear cache
docker exec backend-home-task-php-1 php bin/console cache:clear

# 2. Run the test script
.\dev-resources\test-backend-api-simple.ps1

# 3. Check logs
docker exec backend-home-task-php-1 tail -f /tmp/debricked-*.txt
```

Look for log messages:
- cURL: `"Uploading file to Debricked"`
- Guzzle: `"Uploading file to Debricked (Guzzle)"`

## Recommendation

**For Production:** Start with the **cURL implementation** (current default) since it's battle-tested.

**For Testing & Development:** Use the **Guzzle implementation** for:
- Writing unit tests
- Debugging issues
- Developing new features
- Better code coverage reports

**Migration Path:**
1. ✅ Install Guzzle (done)
2. ✅ Create Guzzle adapter (done)
3. ✅ Write comprehensive tests (done - 16 tests, 100% coverage)
4. ⏳ Run parallel testing in development (current phase)
5. ⏳ Validate in staging environment
6. ⏳ Switch to Guzzle in production
7. ⏳ Deprecate and remove cURL implementation

## Code Locations

- **cURL Adapter:** `src/Service/Provider/DebrickedProviderAdapter.php`
- **Guzzle Adapter:** `src/Service/Provider/DebrickedProviderAdapterGuzzle.php`
- **cURL Tests:** `tests/Service/Provider/DebrickedProviderAdapterTest.php`
- **Guzzle Tests:** `tests/Service/Provider/DebrickedProviderAdapterGuzzleTest.php`
- **Configuration:** `config/services.yaml`

## Dependencies

The Guzzle implementation requires:
```json
{
    "require": {
        "guzzlehttp/guzzle": "^7.10"
    }
}
```

This is already installed and configured.

## Notes

- Both adapters implement `ProviderAdapterInterface`
- Both return identical data structures
- The system automatically injects the active adapter via Symfony's tagged services
- No code changes needed in controllers or message handlers when switching
- Environment variables (DEBRICKED_API_BASE, credentials) remain the same
