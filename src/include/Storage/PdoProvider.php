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
use PDO;
use PDOException;

class PdoProvider implements StorageInterface
{
    private Client $client;

    private array $storage;

    private string $request = '';

    private array $queries;

    private array $result_array = [];

    private ?PDO $db = null;

    public function __construct(Client $client, array $storage)
    {
        $this->client = $client;
        $this->queries = $storage['queries'];
        $this->storage = $storage;
        $this->connect();

        Application::$log->debug('Database connected');
    }

    /**
     * Closes Database connection.
     */
    public function __destruct()
    {
        if ($this->db) {
            $this->db = null;
            Application::$log->debug('Database connection closed');
        }
    }

    /**
     * {@inheritdoc}
     */
    public function get(string $request): array|bool
    {
        if (!$this->db) {
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
     * Connect to DataBase
     *
     * @throws RuntimeException
     */
    private function connect(): self
    {
        try {
            $this->db = new PDO($this->storage['dsn'], $this->storage['db_user'], $this->storage['db_pass']);
        } catch (PDOException $e) {
            throw new RuntimeException('Database connection error');
        }

        return $this;
    }

    /**
     * Query database
     */
    private function query(string $query): array|false
    {
        $query = $this->process_query_string($query);

        if ($query === false) {
            return [];
        }

        $this->db->setAttribute(PDO::ATTR_EMULATE_PREPARES, 1);

        try {
            $sth = $this->db->query($query);

            if ($sth->errorCode() !== '00000') {
                throw new RuntimeException('Error in sql statement');
            }
        } finally {
            $this->db->setAttribute(PDO::ATTR_EMULATE_PREPARES, 0);
        }

        Application::$log->debug('Database ' . __FUNCTION__ . ' query was successful: ' . $query);

        return $sth->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Process SQL query string.
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
            if (strpos($string, "{{$macro}}") !== false) {
                // $value can be a non-string scalar (e.g. an int contact ID)
                // when it comes from $this->result_array - unlike PsqlProvider
                // (pg_fetch_assoc always stringifies), PDO's pgsql driver
                // returns native types for integer/numeric columns.
                $value = mb_strtolower((string) $value);
                $string = str_replace("{{$macro}}", $this->db->quote($value), $string);
            }
        }

        return !preg_match('/\{\w+\}/', $string) ? $string : false;
    }
}
