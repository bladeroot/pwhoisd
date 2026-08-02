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
use Exception;

/**
 * Worker class.
 */
class Worker
{
    private Client $client;

    private Request $request;

    public function __construct(Client $client)
    {
        $this->client = $client;
        $this->request = new Request($client);
    }

    /**
     * Runs a worker to client request processing.
     */
    public function loop(): bool
    {
        $time = time() + Client::READ_TIMEOUT;

        while ($time > time()) {
            $read = $this->client->read();

            if ($read === null) {
                continue;
            }

            if ($read === false) {
                return true; // Malformed request - already logged and blocked in Client::read()
            }

            try {
                if (Application::$security->get_action() == 'drop') {
                    Application::$log->warning('[' . $this->client->get_address() . '] Connection dropped by security');

                    return true;
                }

                $this->request->set_request($read);
                $this->request->process();

                if ($response = $this->request->get_response()) {
                    $this->client->send($response);
                }
            } catch (Exception $e) {
                $this->client->send('Internal error! Please try again later.');

                throw new RuntimeException($e->getMessage());
            }

            return true;
        }

        Application::$log->warning('[' . $this->client->get_address() . '] Request is not readed from client socket');

        return false;
    }

    /**
     * Waits on or returns the status of a forked worker process.
     */
    public function wait(): int
    {
        return pcntl_waitpid(-1, $status, WNOHANG);
    }

    /**
     * Forks the currently running worker process.
     */
    public function fork(): int
    {
        $pid = pcntl_fork();

        if ($pid == -1) {
            $this->client->close();

            exit;
        }

        return $pid;
    }
}
