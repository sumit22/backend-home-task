<?php

namespace App\Tests\Service;

use App\Entity\RepositoryScan;
use App\Message\RepositoryScanStatusChangedMessage;
use App\Service\ScanStateMachine;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Uid\Uuid;

class ScanStateMachineTest extends TestCase
{
    private EntityManagerInterface $em;
    private MessageBusInterface $bus;
    private LoggerInterface $logger;
    private ScanStateMachine $stateMachine;

    protected function setUp(): void
    {
        $this->em = $this->createMock(EntityManagerInterface::class);
        $this->bus = $this->createMock(MessageBusInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        
        $this->stateMachine = new ScanStateMachine(
            $this->em,
            $this->bus,
            $this->logger
        );
    }

    public function testTransitionFromPendingToUploaded(): void
    {
        $scan = new RepositoryScan();
        $scan->setStatus('pending');
        
        $this->em->expects($this->once())
            ->method('flush');
        
        $this->bus->expects($this->once())
            ->method('dispatch')
            ->with($this->isInstanceOf(RepositoryScanStatusChangedMessage::class))
            ->willReturn(new Envelope(new \stdClass()));
        
        $this->stateMachine->transition($scan, 'uploaded', 'Test transition');
        
        $this->assertEquals('uploaded', $scan->getStatus());
    }

    public function testTransitionFromUploadedToRunning(): void
    {
        $scan = new RepositoryScan();
        $scan->setStatus('uploaded');
        
        $this->em->expects($this->once())
            ->method('flush');
        
        $this->bus->expects($this->once())
            ->method('dispatch')
            ->willReturn(new Envelope(new \stdClass()));
        
        $this->stateMachine->transition($scan, 'running');
        
        $this->assertEquals('running', $scan->getStatus());
    }

    public function testTransitionFromRunningToCompleted(): void
    {
        $scan = new RepositoryScan();
        $scan->setStatus('running');
        
        $this->em->expects($this->once())
            ->method('flush');
        
        $this->bus->expects($this->once())
            ->method('dispatch')
            ->willReturn(new Envelope(new \stdClass()));
        
        $this->stateMachine->transition($scan, 'completed');
        
        $this->assertEquals('completed', $scan->getStatus());
    }

    public function testTransitionFromRunningToFailed(): void
    {
        $scan = new RepositoryScan();
        $scan->setStatus('running');
        
        $this->em->expects($this->once())
            ->method('flush');
        
        $this->bus->expects($this->once())
            ->method('dispatch')
            ->willReturn(new Envelope(new \stdClass()));
        
        $this->stateMachine->transition($scan, 'failed', 'Provider error');
        
        $this->assertEquals('failed', $scan->getStatus());
    }

    public function testTransitionFromRunningToTimeout(): void
    {
        $scan = new RepositoryScan();
        $scan->setStatus('running');
        
        $this->em->expects($this->once())
            ->method('flush');
        
        $this->bus->expects($this->once())
            ->method('dispatch')
            ->willReturn(new Envelope(new \stdClass()));
        
        $this->stateMachine->transition($scan, 'timeout');
        
        $this->assertEquals('timeout', $scan->getStatus());
    }

    public function testInvalidTransitionFromPendingToRunning(): void
    {
        $scan = new RepositoryScan();
        $scan->setStatus('pending');
        
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage("Invalid state transition from 'pending' to 'running'");
        
        $this->stateMachine->transition($scan, 'running');
    }

    public function testInvalidTransitionFromCompletedToRunning(): void
    {
        $scan = new RepositoryScan();
        $scan->setStatus('completed');
        
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage("Invalid state transition from 'completed' to 'running'");
        
        $this->stateMachine->transition($scan, 'running');
    }

    public function testInvalidTransitionFromFailedToCompleted(): void
    {
        $scan = new RepositoryScan();
        $scan->setStatus('failed');
        
        $this->expectException(\LogicException::class);
        
        $this->stateMachine->transition($scan, 'completed');
    }

    public function testTransitionToSameStatusIsNoOp(): void
    {
        $scan = new RepositoryScan();
        $scan->setStatus('running');
        
        // No flush or dispatch should happen
        $this->em->expects($this->never())
            ->method('flush');
        
        $this->bus->expects($this->never())
            ->method('dispatch');
        
        $this->stateMachine->transition($scan, 'running');
        
        $this->assertEquals('running', $scan->getStatus());
    }

    public function testCanTransitionReturnsTrueForValidTransitions(): void
    {
        $this->assertTrue($this->stateMachine->canTransition('pending', 'uploaded'));
        $this->assertTrue($this->stateMachine->canTransition('pending', 'failed'));
        $this->assertTrue($this->stateMachine->canTransition('uploaded', 'running'));
        $this->assertTrue($this->stateMachine->canTransition('running', 'completed'));
        $this->assertTrue($this->stateMachine->canTransition('running', 'failed'));
        $this->assertTrue($this->stateMachine->canTransition('running', 'timeout'));
    }

    public function testCanTransitionReturnsFalseForInvalidTransitions(): void
    {
        $this->assertFalse($this->stateMachine->canTransition('pending', 'running'));
        $this->assertFalse($this->stateMachine->canTransition('pending', 'completed'));
        $this->assertFalse($this->stateMachine->canTransition('completed', 'running'));
        $this->assertFalse($this->stateMachine->canTransition('failed', 'completed'));
        $this->assertFalse($this->stateMachine->canTransition('timeout', 'running'));
    }

    public function testGetAvailableTransitions(): void
    {
        $this->assertEquals(['uploaded', 'failed'], $this->stateMachine->getAvailableTransitions('pending'));
        $this->assertEquals(['running', 'failed'], $this->stateMachine->getAvailableTransitions('uploaded'));
        $this->assertEquals(['completed', 'failed', 'timeout'], $this->stateMachine->getAvailableTransitions('running'));
        $this->assertEquals([], $this->stateMachine->getAvailableTransitions('completed'));
        $this->assertEquals([], $this->stateMachine->getAvailableTransitions('failed'));
        $this->assertEquals([], $this->stateMachine->getAvailableTransitions('timeout'));
    }

    public function testIsTerminalState(): void
    {
        $this->assertFalse($this->stateMachine->isTerminalState('pending'));
        $this->assertFalse($this->stateMachine->isTerminalState('uploaded'));
        $this->assertFalse($this->stateMachine->isTerminalState('running'));
        $this->assertTrue($this->stateMachine->isTerminalState('completed'));
        $this->assertTrue($this->stateMachine->isTerminalState('failed'));
        $this->assertTrue($this->stateMachine->isTerminalState('timeout'));
    }

    public function testGetAllStates(): void
    {
        $states = $this->stateMachine->getAllStates();
        
        $this->assertContains('pending', $states);
        $this->assertContains('uploaded', $states);
        $this->assertContains('running', $states);
        $this->assertContains('completed', $states);
        $this->assertContains('failed', $states);
        $this->assertContains('timeout', $states);
    }

    public function testTransitionLogsInfo(): void
    {
        $scan = new RepositoryScan();
        $scan->setStatus('pending');
        
        $this->logger->expects($this->once())
            ->method('info')
            ->with('Scan status transition', $this->callback(function ($context) {
                return $context['from'] === 'pending'
                    && $context['to'] === 'uploaded'
                    && $context['reason'] === 'Test reason';
            }));
        
        $this->em->expects($this->once())
            ->method('flush');
        
        $this->bus->expects($this->once())
            ->method('dispatch')
            ->willReturn(new Envelope(new \stdClass()));
        
        $this->stateMachine->transition($scan, 'uploaded', 'Test reason');
    }

    public function testTransitionLogsErrorOnInvalidTransition(): void
    {
        $scan = new RepositoryScan();
        $scan->setStatus('completed');
        
        $this->logger->expects($this->once())
            ->method('error')
            ->with('Invalid state transition attempted', $this->callback(function ($context) {
                return $context['from'] === 'completed'
                    && $context['to'] === 'running';
            }));
        
        $this->expectException(\LogicException::class);
        
        $this->stateMachine->transition($scan, 'running');
    }

    public function testTransitionDispatchesCorrectMessage(): void
    {
        $scan = new RepositoryScan();
        $scan->setStatus('uploaded');
        
        $this->bus->expects($this->once())
            ->method('dispatch')
            ->with($this->callback(function ($message) use ($scan) {
                return $message instanceof RepositoryScanStatusChangedMessage
                    && $message->getScanId() === $scan->getId()->toRfc4122()
                    && $message->getOldStatus() === 'uploaded'
                    && $message->getNewStatus() === 'running';
            }))
            ->willReturn(new Envelope(new \stdClass()));
        
        $this->em->expects($this->once())
            ->method('flush');
        
        $this->stateMachine->transition($scan, 'running');
    }
}
