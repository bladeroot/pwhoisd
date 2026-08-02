<?php declare(strict_types=1);

namespace PWhoisdTest\Support;

use pWhoisd\Client;
use ReflectionClass;

/**
 * Builds Client instances for tests without calling its constructor - the
 * real constructor requires a live socket resource (socket_set_option(),
 * socket_getpeername()), which unit tests have no business opening.
 */
final class ClientFactory
{
    public static function withAddress(string $address): Client
    {
        $client = (new ReflectionClass(Client::class))->newInstanceWithoutConstructor();

        $property = (new ReflectionClass(Client::class))->getProperty('address');
        $property->setValue($client, $address);

        return $client;
    }
}
