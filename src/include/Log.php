<?php declare(strict_types=1);
/**
 * HSDN PHP Whois Server Daemon
 *
 * @author      HSDN Team
 * @copyright   (c) 2015, Information Networks Ltd.
 * @link        http://www.hsdn.org
 */

namespace pWhoisd;

use pWhoisd\Log\LogAbstract;

/**
 * Log class.
 */
class Log extends LogAbstract
{
    /**
     * Returns instance of Log.
     */
    public static function factory(): self
    {
        return new self();
    }

    /**
     * Assigning class properties.
     */
    public function __construct()
    {
        $this->severity = Application::$config->get('logging.severity', false);
        $this->file = Application::$config->get('logging.file', false);
    }

    /**
     * Adds any message to log.
     */
    public function add(string $message, int $severity = self::info): void
    {
        $message = '['.$this->severities[$severity][0].'] '.$message;

        if ($severity < self::debug || $this->severity >= self::debug) {
            Console::log($message, $this->severities[$severity][1]);
        }

        if ($this->severity && $this->file && $severity <= $this->severity) {
            @file_put_contents($this->file, '['.date('Y-m-d H:i:s').'] '.$message.PHP_EOL, FILE_APPEND);
        }
    }

    /**
     * Gets name of the calling method class
     */
    public function get_calling_class(): ?string
    {
        $trace = debug_backtrace();

        $class = $trace[1]['class'];

        for ($i = 1; $i < count($trace); $i++) {
            if (isset($trace[$i]) && $class != $trace[$i]['class']) {
                return preg_replace('/^'.__NAMESPACE__.'\\\/', '', $trace[$i]['class']);
            }
        }

        return null;
    }
}
