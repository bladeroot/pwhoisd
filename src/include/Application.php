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
use ErrorException;
use Throwable;

define('PWHOISD_VERSION', '0.1.1b');

/**
 * Application class.
 */
class Application extends Daemon
{
    public static ?Config $config = null;

    public static ?Log $log = null;

    public static ?Cache $cache = null;

    public static ?Security $security = null;

    public static ?Server $server = null;

    /**
     * Returns instance of Application.
     */
    public static function factory(): self
    {
        return new self();
    }

    /**
     * Loads command-line arguments, configuration and Server class.
     */
    public function __construct()
    {
        $this->initialize();

        self::$config = new Config();
        self::$log = new Log();
        self::$cache = new Cache();
        self::$security = new Security();
        self::$server = new Server();

        if (!self::$arguments['daemon']) {
            Console::head();
        }

        self::$log->debug('Configuration loaded');
    }

    /**
     * Initialize application
     */
    private function initialize(): void
    {
        set_time_limit(0);

        $this->set_exception_handlers();
        $this->test_dependencies();
        $this->assign_arguments();

        if (!self::$arguments['daemon']) {
            Console::help();
        }

        if (self::$arguments['help']) {
            exit;
        }

        $this->set_identifiers();
        $this->set_signal_handlers();
    }

    /**
     * Sets exception and error handlers
     */
    private function set_exception_handlers(): void
    {
        set_exception_handler([$this, 'exception_handler']);
        set_error_handler([$this, 'error_handler']);
    }

    /**
     * Test application dependencies.
     *
     * @throws RuntimeException If require dependence
     */
    private function test_dependencies(): void
    {
        version_compare(PHP_VERSION, '5.4', '<') and die('Requires PHP 5.4 or newer.');

        if (!extension_loaded('posix')) {
            throw new RuntimeException('Requires POSIX functions support (https://php.net/manual/en/posix.installation.php)');
        }

        if (!extension_loaded('pcntl')) {
            throw new RuntimeException('Requires PCNTL extension (http://www.php.net/manual/en/pcntl.installation.php)');
        }

        if (!extension_loaded('filter')) {
            throw new RuntimeException('Requires filter extension (http://www.php.net/manual/en/filter.installation.php)');
        }

        if (!extension_loaded('gmp') && !extension_loaded('bcmath')) {
            throw new RuntimeException('Requires GMP or BCMATH extension (http://www.php.net/manual/en/gmp.installation.php)');
        }

        if (!extension_loaded('sockets')) {
            throw new RuntimeException('Requires sockets extension (http://www.php.net/manual/en/sockets.installation.php)');
        }
    }

    /**
     * Exception handler method.
     */
    public function exception_handler(Throwable $exception): void
    {
        $message = get_class($exception).': '.$exception->getMessage();
        $log = Application::$log;

        if (is_object($log)) {
            $log->error($message);
        } else {
            Console::log($message, 'red');
        }

        // Terminate process if exception called before server loop
        if (is_null(Application::$server) || Application::$server->listen_loop === false) {
            Application::terminate(false);
        }
    }

    /**
     * Error handler method.
     */
    public function error_handler(int $severity, string $message, string $file, int $line): bool
    {
        if (!(error_reporting() & $severity)) {
            return false;
        }

        // Deprecation notices (e.g. PHP 8.2+ dynamic properties) are forward
        // looking warnings, not actual errors: log and continue instead of
        // aborting, so the daemon keeps working across PHP versions.
        if ($severity === E_DEPRECATED || $severity === E_USER_DEPRECATED) {
            $log = Application::$log;

            if (is_object($log)) {
                $log->debug('PHP Deprecated: '.$message.'; file: '.$file.'; line: '.$line);
            }

            return true;
        }

        throw new ErrorException('PHP Error: '.$message.'; file: '.$file.'; line: '.$line);
    }
}
