<?php declare(strict_types=1);
/**
 * HSDN PHP Whois Server Daemon
 *
 * @author      HSDN Team
 * @copyright   (c) 2015, Information Networks Ltd.
 * @link        http://www.hsdn.org
 */

namespace pWhoisd\Log;

/**
 * Log Interface.
 */
interface LogInterface
{
    /**
     * Adds debug message to log.
     */
    public function debug(string $message): void;

    /**
     * Adds info message to log.
     */
    public function info(string $message): void;

    /**
     * Adds warning message to log.
     */
    public function warning(string $message): void;

    /**
     * Adds error message to log.
     */
    public function error(string $message): void;
}
