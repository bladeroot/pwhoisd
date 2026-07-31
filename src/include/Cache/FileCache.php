<?php declare(strict_types=1);
/**
 * HSDN PHP Whois Server Daemon
 *
 * @author      HiQDev Team
 * @copyright   (c) 2015, Information Networks Ltd.
 * @link        http://www.hsdn.org
 */

namespace pWhoisd\Cache;

use RuntimeException;

/**
 * Filesystem-backed cache. One JSON file per key, written via a temp file +
 * rename so concurrent forked workers never read a half-written entry.
 */
class FileCache implements CacheInterface
{
    private string $path;

    private int $ttl;

    public function __construct(string $path, int $ttl)
    {
        $this->path = rtrim($path, '/');
        $this->ttl = $ttl;

        if (!is_dir($this->path) && !@mkdir($this->path, 0700, true) && !is_dir($this->path)) {
            throw new RuntimeException('could not create cache directory ' . $this->path);
        }
    }

    public function get(string $key): ?array
    {
        $contents = @file_get_contents($this->fileFor($key));

        if ($contents === false) {
            return null;
        }

        $entry = json_decode($contents, true);

        if (!is_array($entry) || !isset($entry['expires_at'], $entry['value'])) {
            return null;
        }

        if ($entry['expires_at'] < time()) {
            @unlink($this->fileFor($key));

            return null;
        }

        return is_array($entry['value']) ? $entry['value'] : null;
    }

    public function set(string $key, array $value): void
    {
        $file = $this->fileFor($key);
        $tmp = $file . '.' . uniqid('', true) . '.tmp';
        $entry = json_encode(['expires_at' => time() + $this->ttl, 'value' => $value]);

        if (@file_put_contents($tmp, $entry, LOCK_EX) === false) {
            @unlink($tmp);

            return;
        }

        rename($tmp, $file);
    }

    private function fileFor(string $key): string
    {
        return $this->path . '/' . preg_replace('/[^A-Za-z0-9_.-]/', '_', $key) . '.json';
    }
}
