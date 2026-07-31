<?php declare(strict_types=1);
/**
 * HSDN PHP Whois Server Daemon
 *
 * @author      HiQDev Team
 * @copyright   (c) 2015, Information Networks Ltd.
 * @link        http://www.hsdn.org
 */

namespace pWhoisd;

use pWhoisd\Cache\CacheInterface;
use pWhoisd\Cache\FileCache;
use pWhoisd\Cache\NullCache;
use pWhoisd\Cache\RedisCache;
use Throwable;

/**
 * Cache facade used by Storage. Selects and builds the configured driver
 * (redis or file); disabled config, an unknown driver, or a driver that
 * fails to initialize all fall back to a no-op NullCache so a cache outage
 * never takes the WHOIS server itself down.
 */
class Cache
{
    private CacheInterface $driver;

    private string $prefix;

    public static function factory(): self
    {
        return new self();
    }

    public function __construct()
    {
        $this->prefix = (string) Application::$config->get('cache.prefix', 'whois:');
        $this->driver = $this->buildDriver();
    }

    public function get(string $key): ?array
    {
        return $this->driver->get($key);
    }

    public function set(string $key, array $value): void
    {
        $this->driver->set($key, $value);
    }

    /**
     * Builds a cache key for a storage query against a request string.
     *
     * Keyed on the storage's own queries (not just $request) so different
     * data sources sharing the same request string never collide.
     *
     * @param array $storage Storage configuration segment
     */
    public function key(array $storage, string $request): string
    {
        $queries = $storage['queries'] ?? [];

        return $this->prefix . md5(json_encode($queries) . '|' . mb_strtolower($request));
    }

    private function buildDriver(): CacheInterface
    {
        if (!Application::$config->get('cache.enabled', false)) {
            return new NullCache();
        }

        $driver = Application::$config->get('cache.driver', 'redis');
        $ttl = (int) Application::$config->get('cache.ttl', 3600);

        try {
            if ($driver === 'redis') {
                if (!extension_loaded('redis')) {
                    throw new \RuntimeException('redis extension is not loaded');
                }

                return new RedisCache(
                    (string) Application::$config->get('cache.host', '127.0.0.1'),
                    (int) Application::$config->get('cache.port', 6379),
                    Application::$config->get('cache.password', null) ?: null,
                    (int) Application::$config->get('cache.database', 0),
                    $ttl
                );
            }

            if ($driver === 'file') {
                return new FileCache(
                    (string) Application::$config->get('cache.path', sys_get_temp_dir() . '/pwhoisd-cache'),
                    $ttl
                );
            }

            throw new \RuntimeException('unknown cache driver "' . $driver . '"');
        } catch (Throwable $exception) {
            Application::$log->warning('Cache: disabled, could not initialize "' . $driver . '" driver: ' . $exception->getMessage());

            return new NullCache();
        }
    }
}
