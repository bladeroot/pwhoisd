<?php declare(strict_types=1);
/**
 * HSDN PHP Whois Server Daemon
 *
 * @author      HSDN Team
 * @copyright   (c) 2015, Information Networks Ltd.
 * @link        http://www.hsdn.org
 */

namespace pWhoisd;

/**
 * Client class.
 */
class Client
{
    /*
     * @var resource|\Socket Worker socket resource. Untyped: PHP has no
     * "resource" type declaration, and PHP 8+ represents an accepted
     * connection as a \Socket object instead.
     */
    private $socket;

    private ?string $address = null;

    private ?int $port = null;

    /**
     * Assigning class properties.
     *
     * @param resource|\Socket $socket Worker socket resource
     */
    public function __construct($socket)
    {
        $this->socket = $socket;

        @socket_getpeername($this->socket, $this->address, $this->port);

        Application::$log->debug('Socket assigned for client ' . $this->address . ':' . $this->port);
        Application::$log->info('[' . $this->address . '] Connected at port ' . $this->port);
    }

    /**
     * Writes message to socket
     */
    public function send(string $message): void
    {
        $message = str_replace(["\r", "\n"], ['', "\r\n"], $message) . "\r\n";

        @socket_write($this->socket, $message, strlen($message));

        Application::$log->info('[' . $this->address . '] Response sended (see debug)');
        Application::$log->debug('Message writed to client socket: ' . PHP_EOL . $message);
    }

    /**
     * Reads data from socket
     */
    public function read(int $len = 1024): ?string
    {
        if (($buffer = @socket_read($this->socket, $len, PHP_BINARY_READ)) === false) {
            return null;
        }

        Application::$log->info('[' . $this->address . '] Request Recieved: ' . $this->sanitize_for_log(trim($buffer)));
        Application::$log->debug('Request readed from client socket: ' . $this->sanitize_for_log($buffer));

        return $buffer;
    }

    /**
     * Strips control characters (including ESC, which starts ANSI escape
     * sequences) from untrusted client input before it goes into the log.
     * WHOIS is a raw TCP protocol with no input validation, so whatever a
     * client sends - garbage, a TLS ClientHello, deliberately crafted
     * terminal escape sequences - gets logged verbatim otherwise, which can
     * corrupt or attack the terminal of whoever is tailing the logs.
     */
    private function sanitize_for_log(string $string): string
    {
        return preg_replace('/[\x00-\x1F\x7F]/', '', $string);
    }

    /**
     * Shutdown and Closes Worker socket
     */
    public function close(): void
    {
        @socket_shutdown($this->socket);
        @socket_close($this->socket);

        Application::$log->debug('Client socket closed');
        Application::$log->info('[' . $this->address . '] Disconnected');
    }

    /**
     * Gets current client IP address
     *
     * Falls back to an empty string (rather than null) if
     * socket_getpeername() failed, since callers (Inet::ip_in_subnets(),
     * Security's rate-limit keys) expect a plain string.
     */
    public function get_address(): string
    {
        return $this->address ?? '';
    }

    /**
     * Gets current client port
     */
    public function get_port(): int
    {
        return $this->port ?? 0;
    }
}
