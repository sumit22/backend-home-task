<?php

namespace App\Tests\Service\Provider;

use App\Contract\Service\ProviderAdapterInterface;
use App\Service\Provider\ProviderManager;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class ProviderManagerTest extends TestCase
{
    private LoggerInterface $logger;

    protected function setUp(): void
    {
        $this->logger = $this->createMock(LoggerInterface::class);
    }

    public function testConstructorRegistersAdapters(): void
    {
        $adapter1 = $this->createMock(ProviderAdapterInterface::class);
        $adapter1->method('providerCode')->willReturn('debricked');

        $adapter2 = $this->createMock(ProviderAdapterInterface::class);
        $adapter2->method('providerCode')->willReturn('snyk');

        $adapters = [$adapter1, $adapter2];

        $manager = new ProviderManager($adapters, $this->logger);

        // Verify adapters are registered
        $this->assertSame($adapter1, $manager->getAdapter('debricked'));
        $this->assertSame($adapter2, $manager->getAdapter('snyk'));
    }

    public function testGetAdapterReturnsCorrectAdapter(): void
    {
        $debrickedAdapter = $this->createMock(ProviderAdapterInterface::class);
        $debrickedAdapter->method('providerCode')->willReturn('debricked');

        $snykAdapter = $this->createMock(ProviderAdapterInterface::class);
        $snykAdapter->method('providerCode')->willReturn('snyk');

        $manager = new ProviderManager([$debrickedAdapter, $snykAdapter], $this->logger);

        $result = $manager->getAdapter('debricked');
        $this->assertSame($debrickedAdapter, $result);

        $result = $manager->getAdapter('snyk');
        $this->assertSame($snykAdapter, $result);
    }

    public function testGetAdapterReturnsNullForUnknownProvider(): void
    {
        $adapter = $this->createMock(ProviderAdapterInterface::class);
        $adapter->method('providerCode')->willReturn('debricked');

        $manager = new ProviderManager([$adapter], $this->logger);

        $result = $manager->getAdapter('unknown_provider');
        $this->assertNull($result);
    }

    public function testGetAdapterWithNullUsesDefaultProvider(): void
    {
        $debrickedAdapter = $this->createMock(ProviderAdapterInterface::class);
        $debrickedAdapter->method('providerCode')->willReturn('debricked');

        $manager = new ProviderManager([$debrickedAdapter], $this->logger);

        // Passing null should default to 'debricked'
        $result = $manager->getAdapter(null);
        $this->assertSame($debrickedAdapter, $result);
    }

    public function testGetAdapterWithNullReturnsNullWhenDefaultNotAvailable(): void
    {
        $snykAdapter = $this->createMock(ProviderAdapterInterface::class);
        $snykAdapter->method('providerCode')->willReturn('snyk');

        $manager = new ProviderManager([$snykAdapter], $this->logger);

        // Passing null defaults to 'debricked', but only 'snyk' is available
        $result = $manager->getAdapter(null);
        $this->assertNull($result);
    }

    public function testConstructorWithEmptyAdapters(): void
    {
        $manager = new ProviderManager([], $this->logger);

        $result = $manager->getAdapter('debricked');
        $this->assertNull($result);

        $result = $manager->getAdapter(null);
        $this->assertNull($result);
    }

    public function testConstructorWithSingleAdapter(): void
    {
        $adapter = $this->createMock(ProviderAdapterInterface::class);
        $adapter->method('providerCode')->willReturn('custom_provider');

        $manager = new ProviderManager([$adapter], $this->logger);

        $result = $manager->getAdapter('custom_provider');
        $this->assertSame($adapter, $result);
    }

    public function testConstructorWithMultipleAdaptersOfSameType(): void
    {
        // If multiple adapters have the same provider code, the last one wins
        $adapter1 = $this->createMock(ProviderAdapterInterface::class);
        $adapter1->method('providerCode')->willReturn('debricked');

        $adapter2 = $this->createMock(ProviderAdapterInterface::class);
        $adapter2->method('providerCode')->willReturn('debricked');

        $manager = new ProviderManager([$adapter1, $adapter2], $this->logger);

        $result = $manager->getAdapter('debricked');
        // Should return the last registered adapter with this code
        $this->assertSame($adapter2, $result);
    }

    public function testGetAdapterIsCaseSensitive(): void
    {
        $adapter = $this->createMock(ProviderAdapterInterface::class);
        $adapter->method('providerCode')->willReturn('debricked');

        $manager = new ProviderManager([$adapter], $this->logger);

        $result = $manager->getAdapter('Debricked'); // Different case
        $this->assertNull($result);

        $result = $manager->getAdapter('debricked'); // Correct case
        $this->assertSame($adapter, $result);
    }

    public function testConstructorHandlesIterableAdapters(): void
    {
        $adapter1 = $this->createMock(ProviderAdapterInterface::class);
        $adapter1->method('providerCode')->willReturn('provider1');

        $adapter2 = $this->createMock(ProviderAdapterInterface::class);
        $adapter2->method('providerCode')->willReturn('provider2');

        $adapter3 = $this->createMock(ProviderAdapterInterface::class);
        $adapter3->method('providerCode')->willReturn('provider3');

        // Test with an ArrayIterator (iterable)
        $adapters = new \ArrayIterator([$adapter1, $adapter2, $adapter3]);

        $manager = new ProviderManager($adapters, $this->logger);

        $this->assertSame($adapter1, $manager->getAdapter('provider1'));
        $this->assertSame($adapter2, $manager->getAdapter('provider2'));
        $this->assertSame($adapter3, $manager->getAdapter('provider3'));
    }
}
