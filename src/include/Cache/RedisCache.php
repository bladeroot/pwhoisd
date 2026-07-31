<?php declare(strict_types=1);
/**
 * HSDN PHP Whois Server Daemon
 *
 * @author      HiQDev Team
 * @copyright   (c) 2015, Information Networks Ltd.
 * @link        http://www.hsdn.org
 */

namespace pWhoisd\Cache;

use pWhoisd\Application;
use Redis;
use RuntimeException;
use Throwable;

/**
 * Redis-backed cache. A per-call failure (connection dropped, timeout, ...)
 * is logged and treated as a miss/no-op instead of throwing.
 */
class RedisCache implements CacheInterface
{
    private Redis $redis;

    private int $ttl;

    public function __construct(string $host, int $port, ?string $password, int $database, int $ttl)
    {
        $this->ttl = $ttl;

        $redis = new Redis();

        if (!$redis->connect($host, $port, 1.0)) {
            throw new RuntimeException('could not connect to Redis at '.$host.':'.$port);
        }

        if ($password) {
            $redis->auth($password);
        }

        $redis->select($database);

        $this->redis = $redis;
    }

    public function get(string $key): ?array
    {
        try {
            $value = $this->redis->get($key);
        } catch (Throwable $exception) {
            $this->logFailure($exception);

            return null;
        }

        if ($value === false) {
            return null;
        }

        $decoded = json_decode($value, true);

        return is_array($decoded) ? $decoded : null;
    }

    public function set(string $key, array $value): void
    {
        try {
            $this->redis->setex($key, $this->ttl, json_encode($value));
        } catch (Throwable $exception) {
            $this->logFailure($exception);
        }
    }

    private function logFailure(Throwable $exception): void
    {
        Application::$log->warning('Cache (redis): '.$exception->getMessage());
    }
}
