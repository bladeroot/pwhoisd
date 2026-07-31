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
 * Socket class.
 */
class Socket
{
    /*
     * @var resource|\Socket|null Server socket resource. Untyped: PHP has no
     * "resource" type declaration, and socket_create() returns a \Socket
     * object on PHP 8+ but a resource on older PHP.
     */
    private $socket;

    private int $domain;

    private string|false $listen_address;

    private int $listen_port;

    /**
     * Assigning class properties and create socket.
     */
    public function __construct(int $domain, string|false $listen_address, int $listen_port)
    {
        $this->domain = $domain;
        $this->listen_address = $listen_address;
        $this->listen_port = $listen_port;
    }

    /**
     * Initialize server.
     */
    public function initialize(): void
    {
        if ($this->domain && $this->listen_address && $this->listen_port) {
            $this->create();
            $this->bind();
            $this->listen();
        }
    }

    /**
     * Accept a socket connection.
     *
     * @return resource|\Socket|false
     */
    public function accept()
    {
        if ($this->is_socket_resource($this->socket)) {
            return @socket_accept($this->socket);
        }

        return false;
    }

    /**
     * Closes a server socket resource.
     */
    public function close(): void
    {
        if ($this->is_socket_resource($this->socket)) {
            @socket_close($this->socket);

            Application::$log->debug('Server socket closed');
        }
    }

    /**
     * Creates a server socket resource and set socket options.
     *
     * @throws RuntimeException If error while creating socket
     */
    protected function create(): void
    {
        $this->socket = @socket_create($this->domain, SOCK_STREAM, SOL_TCP);

        if ($this->socket === false) {
            throw new RuntimeException('Can\'t create socket: '.socket_strerror(socket_last_error()));
        }

        socket_set_option($this->socket, SOL_SOCKET, SO_REUSEADDR, 1);
        socket_set_option($this->socket, SOL_SOCKET, SO_RCVTIMEO, [
            'sec' => 3,
            'usec' => 0,
        ]);

        Application::$log->debug('Server socket created');
    }

    /**
     * Binds a server socket.
     *
     * @throws RuntimeException If error while binding socket
     */
    protected function bind(): void
    {
        if ($this->is_socket_resource($this->socket)) {
            if (@socket_bind($this->socket, $this->listen_address, $this->listen_port) === false) {
                throw new RuntimeException('Can\'t bind socket: '.socket_strerror(socket_last_error($this->socket)));
            }

            Application::$log->debug('Server socket binded');
        }
    }

    /**
     * Listens for a connection on a server socket.
     *
     * @throws RuntimeException If error while listening socket
     */
    protected function listen(): void
    {
        if ($this->is_socket_resource($this->socket)) {
            if (@socket_listen($this->socket, 5) === false) {
                throw new RuntimeException('Can\'t listen: '.socket_strerror(socket_last_error($this->socket)));
            }

            @socket_set_nonblock($this->socket);

            Application::$log->info('Server listening on '.$this->listen_address.':'.$this->listen_port.'...');
        }
    }

    /**
     * Checks whether a value is a usable socket handle.
     *
     * PHP 8+ represents sockets as \Socket objects instead of resources,
     * so is_resource() alone is no longer enough to detect a live socket.
     */
    private function is_socket_resource(mixed $socket): bool
    {
        return is_resource($socket) || $socket instanceof \Socket;
    }
}
