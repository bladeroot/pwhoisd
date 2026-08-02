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
    /**
     * Seconds a connected client has to send its request before being
     * disconnected. Applied both as the accepted socket's own SO_RCVTIMEO
     * (so a blocking socket_read() in read() can't hang forever on a
     * client that connects and then sends nothing) and as Worker::loop()'s
     * overall deadline for receiving a full request.
     */
    public const READ_TIMEOUT = 3;

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

        // Accepted sockets don't inherit the listening socket's options, so
        // without this a client that connects and never sends anything
        // would block socket_read() indefinitely - the daemon forks a
        // worker per connection, so that's an easy resource-exhaustion
        // vector otherwise.
        socket_set_option($this->socket, SOL_SOCKET, SO_RCVTIMEO, [
            'sec'  => self::READ_TIMEOUT,
            'usec' => 0,
        ]);

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
     * Reads data from socket.
     *
     * Returns null while there's nothing to read yet (caller keeps
     * polling), false if a malformed (non-WHOIS) request was rejected and
     * the client blocked - caller should drop the connection without
     * processing it - or the raw request string otherwise.
     */
    public function read(int $len = 1024): string|false|null
    {
        if (($buffer = @socket_read($this->socket, $len, PHP_BINARY_READ)) === false) {
            return null;
        }

        $request = trim($buffer);

        if (!$this->is_valid_request($request)) {
            Application::$log->warning('[' . $this->address . '] Malformed request (' . strlen($buffer) . ' bytes, not printable ASCII) - dropping connection and blocking client');

            Application::$blocklist->block($this->address);
            $this->send($this->invalid_request_message());

            return false;
        }

        Application::$log->info('[' . $this->address . '] Request Recieved: ' . $request);
        Application::$log->debug('Request readed from client socket: ' . $request);

        return $buffer;
    }

    /**
     * A WHOIS request here is a domain name or a help/flag token - always
     * printable ASCII. Anything else (a TLS ClientHello, other binary
     * probes sent straight to the raw TCP port, deliberately crafted
     * terminal escape sequences) is rejected outright: it can't match a
     * real query, would otherwise get logged verbatim and could corrupt or
     * attack the terminal of whoever is tailing the logs.
     */
    private function is_valid_request(string $string): bool
    {
        return $string === '' || preg_match('/^[\x20-\x7E]+$/', $string) === 1;
    }

    /**
     * Message explaining to the client why its connection was blocked,
     * configurable via messages.invalid_data (same array-of-lines format as
     * Security's messages).
     */
    private function invalid_request_message(): string
    {
        $message = Application::$config->get('messages.invalid_data', 'Invalid request format. This connection has been blocked.');

        return is_array($message) ? implode("\n", $message) : (string) $message;
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
