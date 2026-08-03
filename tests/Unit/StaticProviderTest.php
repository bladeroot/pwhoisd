<?php declare(strict_types=1);
/**
 * @author bladeroot@gmail.com, 2026
 */

namespace PWhoisdTest\Unit;

use pWhoisd\Storage\StaticProvider;
use PWhoisdTest\Support\ClientFactory;
use PHPUnit\Framework\TestCase;

final class StaticProviderTest extends TestCase
{
    public function testReturnsDataUnconditionallyWithoutMatch(): void
    {
        $provider = new StaticProvider(ClientFactory::withAddress('203.0.113.1'), [
            'data' => ['RegistrarName' => 'Example Registrar'],
        ]);

        $this->assertSame(['RegistrarName' => 'Example Registrar'], $provider->get('anything at all'));
    }

    public function testReturnsDataWhenRequestMatchesAFieldCaseInsensitively(): void
    {
        $provider = new StaticProvider(ClientFactory::withAddress('203.0.113.1'), [
            'data'  => ['RegistrarName' => 'Example Registrar', 'RegistrarIANAID' => 1234],
            'match' => ['RegistrarName', 'RegistrarIANAID'],
        ]);

        $this->assertSame(
            ['RegistrarName' => 'Example Registrar', 'RegistrarIANAID' => 1234],
            $provider->get('EXAMPLE REGISTRAR')
        );
        $this->assertSame(
            ['RegistrarName' => 'Example Registrar', 'RegistrarIANAID' => 1234],
            $provider->get('1234')
        );
    }

    public function testReturnsEmptyWhenRequestMatchesNoField(): void
    {
        $provider = new StaticProvider(ClientFactory::withAddress('203.0.113.1'), [
            'data'  => ['RegistrarName' => 'Example Registrar'],
            'match' => ['RegistrarName'],
        ]);

        $this->assertSame([], $provider->get('someone else'));
    }
}
