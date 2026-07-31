<?php declare(strict_types=1);
/**
 * HSDN PHP Whois Server Daemon
 *
 * @author      HiQDev Team
 * @copyright   (c) 2015, Information Networks Ltd.
 * @link        http://www.hsdn.org
 */

namespace pWhoisd\Cache;

/**
 * A cache backend for Storage provider results.
 *
 * Implementations must never throw: a cache outage must never take the
 * WHOIS server itself down, so failures are logged and treated as misses.
 */
interface CacheInterface
{
    public function get(string $key): ?array;

    public function set(string $key, array $value): void;
}
