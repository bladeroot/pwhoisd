<?php declare(strict_types=1);
/**
 * HSDN PHP Whois Server Daemon
 *
 * @author      HiQDev Team
 * @author      bladeroot@gmail.com
 * @copyright   (c) 2026, bladeroot@gmail.com
 * @link        http://www.hsdn.org
 */

namespace pWhoisd\Cache;

/**
 * No-op cache used when caching is disabled or a driver failed to initialize.
 */
class NullCache implements CacheInterface
{
    public function get(string $key): ?array
    {
        return null;
    }

    public function set(string $key, array $value): void
    {
    }
}
