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
 * Abstract Daemon class.
 */
abstract class Daemon
{
    /*
     * @var array Command-line arguments defaults
     */
    public static array $arguments = [
        'uid'     => false,
        'gid'     => false,
        'pidfile' => false,
        'daemon'  => false,
        'help'    => false,
        'config'  => 'config.php',
    ];

    /*
     * @var int|null Process identifier
     */
    protected static ?int $pid = null;

    /**
     * Run and listen the server.
     */
    public function run(): void
    {
        Application::$log->debug('Process run as UID/GID: ' . posix_getuid() . '/' . posix_getgid());
        Application::$server->initialize();

        // Transfer process in the background mode
        $this->fork();
        $this->write_pid();

        Application::$server->loop();
    }

    /**
     * Set the UID/GID of the current process
     *
     * @throws RuntimeException If error while changing identifiers
     */
    protected function set_identifiers(): void
    {
        if (is_string(self::$arguments['uid'])) {
            if (!posix_setuid((int) self::$arguments['uid'])) {
                throw new RuntimeException('Can\'t change UID for process');
            }
        }

        if (is_string(self::$arguments['gid'])) {
            if (!posix_setuid((int) self::$arguments['gid'])) {
                throw new RuntimeException('Can\'t change GID for process');
            }
        }
    }

    /**
     * Sets signal processign handlers
     */
    protected function set_signal_handlers(): void
    {
        pcntl_signal(SIGTERM, [__CLASS__, 'terminate']);
        pcntl_signal(SIGINT, [__CLASS__, 'terminate']);
    }

    /**
     * Parse and assigning command-line arguments.
     */
    protected function assign_arguments(): void
    {
        if (isset($GLOBALS['argv']) && is_array($GLOBALS['argv'])) {
            foreach (array_slice($GLOBALS['argv'], 1) as $argument) {
                @[$param, $value] = explode('=', $argument);

                if (substr($param, 0, 2) !== '--') {
                    continue;
                }

                $param = ltrim($param, '-');

                if (isset(self::$arguments[$param])) {
                    self::$arguments[$param] = empty($value) ? true : $value;
                }
            }
        }
    }

    /**
     * Writes pid-file to specified path.
     *
     * @throws RuntimeException If error while creating pid-file
     */
    protected function write_pid(): void
    {
        if (!self::$pid = posix_getpid()) {
            throw new RuntimeException('Can\'t get process ID');
        }

        if (is_string(self::$arguments['pidfile'])) {
            if (!@file_put_contents(self::$arguments['pidfile'], self::$pid . PHP_EOL)) {
                throw new RuntimeException('Can\'t create pid-file for process');
            }

            self::$arguments['pidfile'] = realpath(self::$arguments['pidfile']);
        }
    }

    /**
     * Delete pid-file from specified path.
     *
     * @throws RuntimeException If error while creating pid-file
     */
    protected static function delete_pid(): void
    {
        if (is_string(self::$arguments['pidfile']) && file_exists(self::$arguments['pidfile'])) {
            @unlink(self::$arguments['pidfile']);
        }
    }

    /**
     * Process fork function.
     */
    protected function fork(): void
    {
        if (!self::$arguments['daemon']) {
            return;
        }

        switch ($pid = pcntl_fork()) {
            case -1: throw new RuntimeException('Unable to fork process');
            case 0: break;
            default:
                Application::$log->info('Process running in background mode on PID: ' . $pid);
                exit;
        }

        fclose(STDIN);
        fclose(STDOUT);
        fclose(STDERR);

        $GLOBALS['STDIN'] = fopen('/dev/null', 'r');
        $GLOBALS['STDOUT'] = fopen('/dev/null', 'w');
        $GLOBALS['STDERR'] = fopen('php://stdout', 'w');

        if (posix_setsid() === -1) {
            throw new RuntimeException('Could not set process ID');
        }
    }

    /**
     * Terminate process
     */
    public static function terminate(bool $delete_pid = true): never
    {
        if ($delete_pid) {
            Application::delete_pid();
        }

        if (!is_null(Application::$server)) {
            Application::$server->listen_loop = false;
            Application::$server->close();
        }

        if (!is_null(Application::$log)) {
            Application::$log->debug('Process terminated');
        }

        exit;
    }

    /**
     * Process tick function.
     */
    public static function tick(): void
    {
        pcntl_signal_dispatch();

        usleep(10000);
    }
}
