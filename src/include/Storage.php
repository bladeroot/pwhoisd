<?php declare(strict_types=1);
/**
 * HSDN PHP Whois Server Daemon
 *
 * @author      HSDN Team
 * @copyright   (c) 2015, Information Networks Ltd.
 * @link        http://www.hsdn.org
 */

namespace pWhoisd;

use RuntimeException;

/**
 * Storage class.
 */
class Storage
{
    private Client $client;

    private ?Storage\StorageInterface $provider = null;

    private array $storage;

    public function __construct(Client $client, array $storage)
    {
        $this->client = $client;
        $this->storage = $storage;
    }

    /**
     * Gets response data from storage provider.
     *
     * Checked against the cache before the provider is even loaded, so a
     * cache hit never opens a database connection (or reads a file) at all.
     */
    public function get(string $request): array|bool
    {
        $cache = Application::$cache;
        $cache_key = $cache->key($this->storage, $request);
        $cached = $cache->get($cache_key);

        if (!is_null($cached)) {
            Application::$log->debug('Cache: HIT for "' . $request . '" (key ' . $cache_key . ')');

            return $cached;
        }

        Application::$log->debug('Cache: MISS for "' . $request . '" (key ' . $cache_key . ')');

        $this->load_provider();

        if (is_null($this->provider)) {
            return [];
        }

        $result = $this->provider->get($request);

        if (!empty($result)) {
            $cache->set($cache_key, $result);
        }

        return $result;
    }

    /**
     * Loads storage provider class.
     *
     * @throws RuntimeException If storage provider class does not exists
     */
    private function load_provider(): void
    {
        if (empty($this->storage['type'])) {
            return;
        }

        $type = $this->storage['type'];
        $class = __NAMESPACE__ . '\\Storage\\' . ucfirst($type) . 'Provider';

        if (!class_exists($class)) {
            throw new RuntimeException('Storage provider class "' . $class . '" does not exists');
        }

        $this->provider = new $class($this->client, $this->storage);

        Application::$log->debug('Storage provider "' . $type . '" is loaded');
    }
}
