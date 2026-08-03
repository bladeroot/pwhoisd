<?php declare(strict_types=1);

namespace PWhoisdTest\Unit;

use pWhoisd\Client;
use pWhoisd\Storage\FileProvider;
use pWhoisd\Storage\MysqlProvider;
use pWhoisd\Storage\PdoProvider;
use pWhoisd\Storage\PsqlProvider;
use pWhoisd\Storage\StaticProvider;
use pWhoisd\Storage\StorageInterface;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionNamedType;

/**
 * Live DB/file round-trips for the storage providers need a running
 * Postgres/MySQL server or fixture files and are out of scope here (see
 * Storage::load_provider(), which is what actually wires a config's
 * 'type' string to one of these classes at runtime). This instead locks
 * down the contract Storage::load_provider() depends on:
 * StorageInterface::get() plus a Client+array constructor, for every
 * class it's able to instantiate via `new $class($client, $storage)`.
 */
final class StorageProviderContractTest extends TestCase
{
    /**
     * @dataProvider providerClasses
     */
    public function testImplementsStorageInterface(string $class): void
    {
        $this->assertTrue(
            is_subclass_of($class, StorageInterface::class),
            $class . ' must implement ' . StorageInterface::class
        );
    }

    /**
     * @dataProvider providerClasses
     */
    public function testGetMethodMatchesTheInterfaceSignature(string $class): void
    {
        $interfaceMethod = new \ReflectionMethod(StorageInterface::class, 'get');
        $method = new \ReflectionMethod($class, 'get');

        $this->assertTrue($method->isPublic(), $class . '::get() must be public');
        $this->assertSame(
            $this->describeParameters($interfaceMethod),
            $this->describeParameters($method),
            $class . '::get() parameters must match StorageInterface::get()'
        );
        $this->assertSame(
            (string) $interfaceMethod->getReturnType(),
            (string) $method->getReturnType(),
            $class . '::get() return type must match StorageInterface::get()'
        );
    }

    /**
     * @dataProvider providerClasses
     */
    public function testConstructorAcceptsClientAndStorageArray(string $class): void
    {
        $constructor = (new ReflectionClass($class))->getConstructor();

        $this->assertNotNull($constructor, $class . ' must declare a constructor');

        $parameters = $constructor->getParameters();

        $this->assertCount(2, $parameters, $class . '::__construct() must take exactly 2 parameters');

        $this->assertSame(Client::class, $this->typeName($parameters[0]->getType()));
        $this->assertSame('array', $this->typeName($parameters[1]->getType()));
    }

    /**
     * Storage::load_provider() resolves a config 'type' to a class via
     * __NAMESPACE__ . '\Storage\' . ucfirst($type) . 'Provider' - this
     * pins that naming convention for every type the shipped configs
     * (whois.danesconames.com, whois.domaincontext.com) actually use.
     *
     * @dataProvider knownStorageTypes
     */
    public function testStorageTypeResolvesToAnExistingProviderClass(string $type, string $expectedClass): void
    {
        $resolved = 'pWhoisd\\Storage\\' . ucfirst($type) . 'Provider';

        $this->assertSame($expectedClass, $resolved);
        $this->assertTrue(class_exists($resolved), $resolved . ' referenced by storage type "' . $type . '" must exist');
    }

    public static function providerClasses(): array
    {
        return [
            [PsqlProvider::class],
            [PdoProvider::class],
            [MysqlProvider::class],
            [FileProvider::class],
            [StaticProvider::class],
        ];
    }

    public static function knownStorageTypes(): array
    {
        return [
            'psql'   => ['psql', PsqlProvider::class],
            'pdo'    => ['pdo', PdoProvider::class],
            'mysql'  => ['mysql', MysqlProvider::class],
            'file'   => ['file', FileProvider::class],
            'static' => ['static', StaticProvider::class],
        ];
    }

    /**
     * @return string[]
     */
    private function describeParameters(\ReflectionMethod $method): array
    {
        return array_map(
            fn ($p) => $this->typeName($p->getType()) . ' $' . $p->getName(),
            $method->getParameters()
        );
    }

    private function typeName(?\ReflectionType $type): string
    {
        return $type instanceof ReflectionNamedType ? $type->getName() : (string) $type;
    }
}
