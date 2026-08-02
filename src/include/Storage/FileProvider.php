<?php declare(strict_types=1);
/**
 * HSDN PHP Whois Server Daemon
 *
 * @author      HSDN Team
 * @author      HiQDev
 * @copyright   (c) 2015, Information Networks Ltd.
 * @link        http://www.hsdn.org
 */

namespace pWhoisd\Storage;

use pWhoisd\Application;
use pWhoisd\Client;
use RuntimeException;

class FileProvider implements StorageInterface
{
    private Client $client;

    private string $request = '';

    private array $queries;

    private array $result_array = [];

    private string $path;

    /**
     * Assigning class properties and connect to Database.
     *
     * @throws RuntimeException If Repository does not exist
     */
    public function __construct(Client $client, array $storage)
    {
        $this->client = $client;
        $this->queries = $storage['queries'];
        $this->path = $storage['storage'];

        if (!is_dir($this->path)) {
            throw new RuntimeException('Path to files does not exist');
        }

        if (!is_readable($this->path)) {
            throw new RuntimeException('Path to files is not readable');
        }

        Application::$log->debug('Path find');
    }

    public function __destruct()
    {
    }

    /**
     * {@inheritdoc}
     */
    public function get(string $request): array|bool
    {
        if (!$this->path) {
            return false;
        }

        $this->request = $request;

        foreach ($this->queries as $query) {
            if ($result = $this->query($query)) {
                $this->result_array += $result;
            }
        }

        return $this->result_array;
    }

    /**
     * Find file with data
     */
    private function query(string $query): array
    {
        $query = $this->process_query_string($query);

        if ($query === false) {
            return [];
        }

        $md5 = md5(mb_strtolower($query));

        $local_path = $this->colculatePath($md5);
        $path = $this->path . DIRECTORY_SEPARATOR . $local_path;

        if (!is_readable($path)) {
            return [];
        }

        $decoded = json_decode(file_get_contents($path), true);

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * Process query string.
     */
    private function process_query_string(string $string): string|false
    {
        // System macro
        $macros = [
            '_request_'     => str_replace(['%', '_'], '', $this->request),
            '_client_ip_'   => $this->client->get_address(),
            '_client_port_' => $this->client->get_port(),
        ];

        // Storage response macro
        if (!empty($this->result_array)) {
            $macros += $this->result_array;
        }

        foreach ($macros as $macro => $value) {
            if (strpos($string, '{' . $macro . '}') !== false) {
                // $value can be a non-string scalar from $this->result_array.
                $string = str_replace('{' . $macro . '}', (string) $value, $string);
            }
        }

        return !preg_match('/\{\w+\}/', $string) ? $string : false;
    }

    /**
     * Calculate path to file
     */
    private function colculatePath(string $md5): string
    {
        return substr($md5, 0, 1) . DIRECTORY_SEPARATOR . substr($md5, 1, 1) . DIRECTORY_SEPARATOR . $md5;
    }
}
