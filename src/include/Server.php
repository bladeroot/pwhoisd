<?php declare(strict_types=1);
/**
 * HSDN PHP Whois Server Daemon
 *
 * @author      HSDN Team
 * @copyright   (c) 2015, Information Networks Ltd.
 * @link        http://www.hsdn.org
 */

namespace pWhoisd;

use Exception;
use RuntimeException;

/**
 * Server class.
 */
class Server
{
    private Socket $socket;

    private Socket $socket_ipv6;

    public bool $listen_loop = false;

    /**
     * Assigning class properties and create socket.
     */
    public function __construct()
    {
        $listen_port = Application::$config->get('daemon.listen_port', 43);

        $this->socket = new Socket(AF_INET, Application::$config->get('daemon.listen_address', false), $listen_port);
        $this->socket_ipv6 = new Socket(AF_INET6, Application::$config->get('daemon.listen_address_ipv6', false), $listen_port);
    }

    /**
     * Initialize server.
     */
    public function initialize(): void
    {
        $this->socket->initialize();
        $this->socket_ipv6->initialize();
    }

    /**
     * Runs a main loop for accepting server requests.
     *
     * @throws RuntimeException If error while accept connections
     * @throws RuntimeException If error called in Worker
     */
    public function loop(): void
    {
        Application::$log->info('Server ready to accept connections');

        $this->listen_loop = true;

        $worker_processes = [];

        while ($this->listen_loop) {
            Application::tick();

            if (($client_socket = $this->accept()) === false) {
                continue;
            }

            Application::$log->debug('Server socket accept new connection');
            Application::$log->debug('Client socket created');

            $client = new Client($client_socket);
            $worker = new Worker($client);

            while ($pid = $worker->wait()) {
                if ($pid == -1) {
                    $worker_processes = [];

                    break;
                }

                unset($worker_processes[$pid]);

                // Forces collection of any existing garbage cycles
                gc_collect_cycles();
            }

            if (count($worker_processes) > Application::$config->get('daemon.workers')) {
                Application::$log->warning('[' . $client->get_address() . '] Workers limit exceded');

                $client->close();

                continue;
            }

            try {
                Application::$security->initialize($client);

                if ($pid = $worker->fork()) {
                    $worker_processes[$pid] = true;

                    Application::$log->debug('Worker process created');

                    continue;
                }

                $worker->loop();
                $client->close();

                Application::$log->debug('Worker process terminated');

                exit;
            } catch (Exception $e) {
                $client->close();

                throw new RuntimeException($e->getMessage());
            }
        }
    }

    /**
     * Closes a server socket resource.
     */
    public function close(): void
    {
        $this->socket->close();
        $this->socket_ipv6->close();
    }

    /**
     * Accept connection
     *
     * @return resource|\Socket|false
     */
    private function accept()
    {
        if (($socket = $this->socket->accept()) === false) {
            if (($socket = $this->socket_ipv6->accept()) === false) {
                return false;
            }
        }

        return $socket;
    }
}
