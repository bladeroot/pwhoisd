<?php declare(strict_types=1);
/**
 * HSDN PHP Whois Server Daemon
 *
 * @author      HiQDev Team
 * @copyright   (c) 2015, Information Networks Ltd.
 * @link        http://www.hsdn.org
 */

namespace pWhoisd\Storage;

use pWhoisd\Client;

/**
 * Returns a fixed row straight from config - no DB/file/network I/O at all.
 * For data that's genuinely constant (e.g. this deployment's own registrar
 * identity), matching a real per-row lookup's shape without paying for one:
 *
 *   'storage' => [
 *       'type'  => 'static',
 *       'data'  => ['RegistrarName' => 'Example Registrar', ...],
 *       'match' => ['RegistrarName'], // optional; case-insensitive
 *   ],
 *
 * Without 'match', 'data' is always returned. With it, 'data' is returned
 * only if the request case-insensitively equals one of the named fields'
 * values - otherwise an empty result, same as a query that matched no rows.
 */
class StaticProvider implements StorageInterface
{
    private array $data;

    private array $matchFields;

    public function __construct(Client $client, array $storage)
    {
        $this->data = $storage['data'] ?? [];
        $this->matchFields = $storage['match'] ?? [];
    }

    /**
     * {@inheritdoc}
     */
    public function get(string $request): array|bool
    {
        if (empty($this->matchFields)) {
            return $this->data;
        }

        $request = mb_strtolower($request);

        foreach ($this->matchFields as $field) {
            if (isset($this->data[$field]) && mb_strtolower((string) $this->data[$field]) === $request) {
                return $this->data;
            }
        }

        return [];
    }
}
