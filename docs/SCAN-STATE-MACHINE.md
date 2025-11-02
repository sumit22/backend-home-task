# Scan Status State Machine

## Overview

The `ScanStateMachine` service implements a finite state machine (FSM) to manage `RepositoryScan` status transitions. This ensures data integrity by preventing invalid state transitions and provides automatic event dispatching and logging.

## State Diagram

```
pending
  ├─> uploaded
  └─> failed

uploaded
  ├─> queued
  └─> failed

queued
  ├─> running
  └─> failed

running
  ├─> completed (terminal)
  ├─> failed (terminal)
  └─> timeout (terminal)
```

## States

### Active States
- **pending** - Initial state when scan is created
- **uploaded** - Files have been uploaded to storage
- **queued** - Scan queued for provider processing
- **running** - Scan is actively being processed by provider

### Terminal States
- **completed** - Scan finished successfully
- **failed** - Scan failed due to error
- **timeout** - Scan exceeded maximum polling attempts

## Usage

### Basic Transition

```php
use App\Service\ScanStateMachine;

class MyService
{
    public function __construct(
        private ScanStateMachine $stateMachine
    ) {}

    public function processUpload(RepositoryScan $scan): void
    {
        // Transition from 'pending' to 'uploaded'
        $this->stateMachine->transition(
            $scan,
            'uploaded',
            'All files uploaded successfully'
        );
    }
}
```

### Check Valid Transitions

```php
// Check if transition is allowed
if ($stateMachine->canTransition('pending', 'running')) {
    // This will return false - invalid transition
}

if ($stateMachine->canTransition('pending', 'uploaded')) {
    // This will return true - valid transition
}
```

### Get Available Transitions

```php
$available = $stateMachine->getAvailableTransitions('running');
// Returns: ['completed', 'failed', 'timeout']
```

### Check Terminal States

```php
if ($stateMachine->isTerminalState('completed')) {
    // Returns true - no further transitions allowed
}
```

## Automatic Features

### 1. Event Dispatching

When a transition occurs, the state machine automatically dispatches a `RepositoryScanStatusChangedMessage`:

```php
new RepositoryScanStatusChangedMessage(
    scanId: $scan->getId()->toRfc4122(),
    oldStatus: 'running',
    newStatus: 'completed'
);
```

This triggers:
- **Rule evaluation** via `RepositoryScanStatusChangedHandler`
- **Notification sending** based on active rules
- **Async processing** through Symfony Messenger

### 2. Logging

All transitions are automatically logged:

```php
// Successful transition
$logger->info('Scan status transition', [
    'scan_id' => '123e4567-e89b-12d3-a456-426614174000',
    'from' => 'running',
    'to' => 'completed',
    'reason' => 'Provider scan completed successfully'
]);

// Invalid transition attempt
$logger->error('Invalid state transition attempted', [
    'scan_id' => '123e4567-e89b-12d3-a456-426614174000',
    'from' => 'completed',
    'to' => 'running',
    'reason' => null
]);
```

### 3. Database Persistence

The state machine automatically calls `EntityManager::flush()` after updating the status, ensuring changes are persisted immediately.

### 4. No-Op for Same Status

Attempting to transition to the current status is a no-op (no database changes, no events dispatched).

## Validation

### Exception on Invalid Transition

```php
try {
    $stateMachine->transition($scan, 'running', 'Invalid transition');
} catch (\LogicException $e) {
    // Message: "Invalid state transition from 'completed' to 'running'. 
    // Allowed transitions: [none (terminal state)]"
}
```

### Terminal State Protection

Once a scan reaches a terminal state (`completed`, `failed`, `timeout`), no further transitions are allowed:

```php
$scan->setStatus('completed');
$stateMachine->transition($scan, 'running'); // Throws LogicException
```

## Migration from Direct setStatus()

### Before (Direct setStatus)

```php
// ❌ Old approach - no validation, no events
$scan->setStatus('running');
$em->flush();
```

### After (State Machine)

```php
// ✅ New approach - validated, events dispatched, logged
$stateMachine->transition($scan, 'running', 'Provider scan started');
```

## Implementation Details

### Services Using State Machine

1. **ScanService** - File upload completion, manual scan execution
2. **StartProviderScanHandler** - Provider scan initialization, error handling
3. **PollProviderScanResultsHandler** - Scan completion, timeout, error handling

### Testing

The state machine has 18 comprehensive unit tests covering:
- All valid transitions
- Invalid transition detection
- Terminal state protection
- Event dispatching
- Logging behavior
- Helper methods

Run tests:
```bash
docker exec backend-home-task-php-1 php bin/phpunit tests/Service/ScanStateMachineTest.php
```

## Benefits

### 1. Data Integrity
- Prevents impossible state transitions (e.g., `completed` → `pending`)
- Ensures workflow follows defined path
- Catches logic errors early

### 2. Observability
- All transitions logged with context
- Easy to debug state-related issues
- Audit trail of status changes

### 3. Event-Driven Architecture
- Automatic event dispatching
- Decouples status changes from side effects
- Enables async notification processing

### 4. Maintainability
- Centralized state logic
- Single source of truth for valid transitions
- Easy to add new states or transitions

### 5. Testability
- State machine independently testable
- Easy to mock in dependent services
- Clear contract for state transitions

## Future Enhancements

### Potential Improvements
1. **Audit Trail** - Store state transitions in dedicated table
2. **Retry Logic** - Automatic retry for transient failures
3. **Conditional Transitions** - Guards based on scan properties
4. **Parallel States** - Support for sub-states (e.g., `running.uploading`, `running.analyzing`)
5. **Time-Based Transitions** - Automatic timeout after X minutes in `running` state
6. **Symfony Workflow Component** - Migrate to Symfony's workflow component for advanced features

### Example: Audit Trail

```php
class ScanStateTransition
{
    private Uuid $id;
    private RepositoryScan $scan;
    private string $fromStatus;
    private string $toStatus;
    private ?string $reason;
    private \DateTimeImmutable $transitionedAt;
}
```

## References

- [Symfony Workflow Component](https://symfony.com/doc/current/workflow.html)
- [Finite State Machine Pattern](https://en.wikipedia.org/wiki/Finite-state_machine)
- [State Pattern](https://refactoring.guru/design-patterns/state)
