<?php declare(strict_types=1);
/**
 * HSDN PHP Whois Server Daemon
 *
 * @author      HiQDev Team
 * @copyright   (c) 2015, Information Networks Ltd.
 * @link        http://www.hsdn.org
 */

namespace pWhoisd;

/**
 * Tracks client IPs temporarily blocked for sending malformed (non-WHOIS)
 * input - e.g. a TLS ClientHello or other binary probe sent straight to the
 * raw TCP port. Backed by a single shared file (not the optional query
 * Cache) so blocking works even when the cache is disabled, and so a block
 * recorded by one forked worker process is visible to the next connection's
 * Security::initialize() call in the long-lived parent process.
 */
class Blocklist
{
    private bool $enabled;

    private string $path;

    private int $ttl;

    public function __construct()
    {
        $this->enabled = (bool) Application::$config->get('security.blocklist.enabled', true);
        $this->path = (string) Application::$config->get('security.blocklist.path', sys_get_temp_dir() . '/pwhoisd-blocklist.json');
        $this->ttl = (int) Application::$config->get('security.blocklist.ttl', 36000);
    }

    /**
     * Whether the given IP is currently blocked.
     */
    public function is_blocked(string $ip): bool
    {
        if (!$this->enabled || $ip === '') {
            return false;
        }

        return isset($this->read()[$ip]);
    }

    /**
     * Blocks the given IP for the configured TTL, starting now.
     */
    public function block(string $ip): void
    {
        if (!$this->enabled || $ip === '') {
            return;
        }

        $this->withLock(function (array $entries) use ($ip): array {
            $entries[$ip] = time() + $this->ttl;

            return $entries;
        });
    }

    /**
     * Reads currently-valid (non-expired) entries, without locking - used
     * for the read-only is_blocked() check.
     *
     * @return array<string, int>
     */
    private function read(): array
    {
        $contents = @file_get_contents($this->path);

        if ($contents === false) {
            return [];
        }

        return $this->prune(json_decode($contents, true));
    }

    /**
     * Opens the blocklist file under an exclusive lock, lets the callback
     * transform the current (pruned) entries, and writes the result back
     * before releasing the lock - so concurrent block() calls from
     * different forked workers never clobber each other.
     *
     * @param callable(array<string, int>): array<string, int> $transform
     */
    private function withLock(callable $transform): void
    {
        $handle = @fopen($this->path, 'c+');

        if ($handle === false) {
            return;
        }

        try {
            if (!flock($handle, LOCK_EX)) {
                return;
            }

            $contents = stream_get_contents($handle);
            $entries = $this->prune($contents === false ? null : json_decode($contents, true));

            $entries = $transform($entries);

            ftruncate($handle, 0);
            rewind($handle);
            fwrite($handle, json_encode($entries));
            fflush($handle);
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }

    /**
     * Drops expired entries from a decoded (or malformed/missing) entry map.
     *
     * @return array<string, int>
     */
    private function prune(mixed $decoded): array
    {
        if (!is_array($decoded)) {
            return [];
        }

        $now = time();

        return array_filter($decoded, static fn ($expires_at) => is_int($expires_at) && $expires_at > $now);
    }
}
