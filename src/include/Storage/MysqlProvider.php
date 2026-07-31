<?php declare(strict_types=1);
/**
 * HSDN PHP Whois Server Daemon
 *
 * @author      HSDN Team
 * @copyright   (c) 2015, Information Networks Ltd.
 * @link        http://www.hsdn.org
 */

namespace pWhoisd\Storage;

use pWhoisd\Application;
use pWhoisd\Client;
use RuntimeException;
use mysqli;

class MysqlProvider implements StorageInterface
{
    private Client $client;

    private string $request = '';

    private array $queries;

    private array $result_array = [];

    private mysqli $db;

    /**
     * Assigning class properties and connect to Database.
     *
     * @throws RuntimeException If MySQL Connection error
     */
    public function __construct(Client $client, array $storage)
    {
        $this->client = $client;
        $this->queries = $storage['queries'];

        $this->db = @new mysqli(
            $storage['db_host'],
            $storage['db_user'],
            $storage['db_pass'],
            $storage['db_name']
        );

        if ($this->db->connect_errno) {
            throw new RuntimeException('Database connection error: ' . $this->db->connect_error);
        } else {
            $this->db->set_charset(Application::$config->get('storage.db_charset', 'utf8'));
        }

        Application::$log->debug('Database connected');
    }

    /**
     * Closes Database connection.
     */
    public function __destruct()
    {
        if (!$this->db->connect_errno) {
            $this->db->close();

            Application::$log->debug('Database connection closed');
        }
    }

    /**
     * {@inheritdoc}
     */
    public function get(string $request): array|bool
    {
        if ($this->db->connect_errno) {
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
     * Query database
     */
    private function query(string $query): array
    {
        $query = $this->process_query_string($query);

        $array = [];

        if ($query !== false) {
            if (!$result = $this->db->query($query)) {
                throw new RuntimeException('Database ' . __FUNCTION__ . ' qyery error: ' . $this->db->error);
            }

            if ($result->num_rows) {
                $array = $result->fetch_array(MYSQLI_ASSOC);
            }

            Application::$log->debug('Database ' . __FUNCTION__ . ' query was successful: ' . $query);

            $result->close();
        }

        return $array;
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
            if (strpos($string, '{' . $macro . '}') !== false) {
                $string = str_replace('{' . $macro . '}', $this->db->real_escape_string($value), $string);
            }
        }

        return !preg_match('/\{\w+\}/', $string) ? $string : false;
    }
}
