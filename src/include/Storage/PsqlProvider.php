<?php declare(strict_types=1);
/**
 * HSDN PHP Whois Server Daemon
 *
 * @author      HSDN Team
 * @author      HiQDev Team
 * @copyright   (c) 2015, Information Networks Ltd.
 * @link        http://www.hsdn.org
 */

namespace pWhoisd\Storage;

use pWhoisd\Application;
use pWhoisd\Client;
use RuntimeException;

class PsqlProvider implements StorageInterface
{
    private Client $client;

    private array $storage;

    private ?string $request = null;

    private array $queries;

    private array $resultArray = [];

    /**
     * @var resource|\PgSql\Connection
     */
    private $db;

    /**
     * Assigning class properties and connect to Database.
     *
     * @throws RuntimeException If PgSQL connection error
     */
    public function __construct(Client $client, array $storage)
    {
        $this->client = $client;
        $this->storage = $storage;
        $this->queries = $storage['queries'];

        error_clear_last();

        $this->db = @pg_connect(implode(' ', [
            "dbname={$storage['db_name']}",
            "user={$storage['db_user']}",
            "password={$storage['db_pass']}",
            "host={$storage['db_host']}",
            "port={$storage['db_port']}",
        ]));

        if (!$this->isPgsqlResource($this->db)) {
            $error = error_get_last();
            $message = isset($error['message']) ? preg_replace('/^pg_connect\(\):\s*/', '', $error['message']) : '';

            throw new RuntimeException('Database connection error' . ($message ? ': ' . $message : ''));
        }

        Application::$log->debug('Database connected');
    }

    public function __destruct()
    {
        if ($this->isPgsqlResource($this->db)) {
            pg_close($this->db);

            Application::$log->debug('Database connection closed');
        }
    }

    /**
     * {@inheritdoc}
     */
    public function get(string $request): array|bool
    {
        if (!$this->isPgsqlResource($this->db)) {
            return false;
        }

        $this->request = $request;

        foreach ($this->queries as $query) {
            if ($result = $this->query($query)) {
                $this->resultArray += $result;
            }
        }

        return $this->resultArray;
    }

    /**
     * Checks whether a value is a usable PostgreSQL connection handle.
     *
     * PHP 8+ represents PostgreSQL connections as \PgSql\Connection objects
     * instead of resources.
     *
     * @param mixed $db
     */
    private function isPgsqlResource($db): bool
    {
        return is_resource($db) || $db instanceof \PgSql\Connection;
    }

    private function query(string $query): array
    {
        $start = time();
        $query = $this->processQueryString($query);

        $array = [];

        if ($query !== false) {
            $result = pg_query($this->db, $query);

            if (!$result) {
                throw new RuntimeException('Database query error: ' . pg_last_error($this->db));
            }

            if (pg_num_rows($result)) {
                $array = pg_fetch_assoc($result);
            }

            Application::$log->debug('Database query was successful: ' . $query);
        }

        Application::$log->debug('Execution time is ' . (time() - $start) . 's');

        return $array;
    }

    private function processQueryString(string $string)
    {
        $macros = [
            '_request_'     => str_replace(['%', '_'], '', $this->request),
            '_client_ip_'   => $this->client->get_address(),
            '_client_port_' => $this->client->get_port(),
        ];

        if (!empty($this->resultArray)) {
            $macros += $this->resultArray;
        }

        foreach ($macros as $macro => $value) {
            if (strpos($string, '{' . $macro . '}') !== false) {
                // $value can be a non-string scalar from $this->resultArray
                // (e.g. an int ID) in principle, even though pg_fetch_assoc
                // itself always returns strings today.
                $string = str_replace('{' . $macro . '}', pg_escape_string($this->db, mb_strtolower((string) $value)), $string);
            }
        }

        return !preg_match('/\{\w+\}/', $string) ? $string : false;
    }
}
