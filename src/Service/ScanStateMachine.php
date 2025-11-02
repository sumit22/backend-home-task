<?php

namespace App\Service;

use App\Entity\RepositoryScan;
use App\Message\RepositoryScanStatusChangedMessage;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Finite State Machine for RepositoryScan status transitions.
 * 
 * Manages valid state transitions and ensures integrity of scan workflow.
 * Automatically dispatches status change events and logs transitions.
 */
class ScanStateMachine
{
    /**
     * Valid state transitions mapping.
     * Each key is a current state, values are allowed next states.
     */
    private const VALID_TRANSITIONS = [
        'pending' => ['uploaded', 'failed'],
        'uploaded' => ['running', 'failed'],
        'running' => ['completed', 'failed', 'timeout'],
        'completed' => [],  // Terminal state
        'failed' => [],     // Terminal state
        'timeout' => [],    // Terminal state
    ];

    public function __construct(
        private EntityManagerInterface $em,
        private MessageBusInterface $bus,
        private LoggerInterface $logger
    ) {}

    /**
     * Transition a scan to a new status.
     * 
     * Validates the transition, updates the entity, dispatches events,
     * and persists the change.
     * 
     * @param RepositoryScan $scan The scan to transition
     * @param string $newStatus The target status
     * @param string|null $reason Optional reason for the transition
     * @throws \LogicException If the transition is not allowed
     */
    public function transition(RepositoryScan $scan, string $newStatus, ?string $reason = null): void
    {
        $oldStatus = $scan->getStatus();
        
        // No-op if already in target state
        if ($oldStatus === $newStatus) {
            $this->logger->debug('Scan already in target status', [
                'scan_id' => $scan->getId()?->toRfc4122(),
                'status' => $newStatus,
            ]);
            return;
        }

        // Validate transition
        if (!$this->canTransition($oldStatus, $newStatus)) {
            $allowed = implode(', ', $this->getAvailableTransitions($oldStatus));
            $message = sprintf(
                "Invalid state transition from '%s' to '%s'. Allowed transitions: [%s]",
                $oldStatus,
                $newStatus,
                $allowed ?: 'none (terminal state)'
            );
            
            $this->logger->error('Invalid state transition attempted', [
                'scan_id' => $scan->getId()?->toRfc4122(),
                'from' => $oldStatus,
                'to' => $newStatus,
                'reason' => $reason,
            ]);
            
            throw new \LogicException($message);
        }

        // Perform transition
        $scan->setStatus($newStatus);
        
        // Log transition
        $this->logger->info('Scan status transition', [
            'scan_id' => $scan->getId()?->toRfc4122(),
            'from' => $oldStatus,
            'to' => $newStatus,
            'reason' => $reason,
        ]);

        // Persist changes
        $this->em->flush();

        // Dispatch status change event for async processing (notifications, rules, etc.)
        $this->bus->dispatch(new RepositoryScanStatusChangedMessage(
            $scan->getId()->toRfc4122(),
            $oldStatus,
            $newStatus
        ));
    }

    /**
     * Check if a transition from one status to another is valid.
     * 
     * @param string $from Current status
     * @param string $to Target status
     * @return bool True if transition is allowed
     */
    public function canTransition(string $from, string $to): bool
    {
        return isset(self::VALID_TRANSITIONS[$from]) 
            && in_array($to, self::VALID_TRANSITIONS[$from], true);
    }

    /**
     * Get all valid next states from a given status.
     * 
     * @param string $status Current status
     * @return array List of allowed next states
     */
    public function getAvailableTransitions(string $status): array
    {
        return self::VALID_TRANSITIONS[$status] ?? [];
    }

    /**
     * Check if a status is a terminal state (no further transitions allowed).
     * 
     * @param string $status Status to check
     * @return bool True if terminal state
     */
    public function isTerminalState(string $status): bool
    {
        return empty(self::VALID_TRANSITIONS[$status] ?? null);
    }

    /**
     * Get all possible states in the system.
     * 
     * @return array List of all valid states
     */
    public function getAllStates(): array
    {
        return array_keys(self::VALID_TRANSITIONS);
    }
}
