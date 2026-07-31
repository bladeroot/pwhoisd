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
 * Autoloader class.
 */
class Autoloader
{
    /**
     * Register the autoload callback
     */
    public static function register(): void
    {
        ini_set('unserialize_callback_func', 'spl_autoload_call');

        spl_autoload_register([__CLASS__, 'autoload']);
    }

    /**
     * Autoload callback
     */
    public static function autoload(string $class): void
    {
        $class_pos = -strpos(strrev($class), '\\');
        $class_name = substr($class, $class_pos);
        $namespace = substr($class, 0, $class_pos);

        if (strpos($namespace, __NAMESPACE__) !== 0) {
            return;
        }

        $namespace = substr($namespace, strlen(__NAMESPACE__) + 1);
        $class_file = str_replace(['\\', '_'], DIRECTORY_SEPARATOR, $namespace . $class_name) . '.php';
        $class_path = INCLUDE_PATH . DIRECTORY_SEPARATOR . $class_file;

        if (!is_readable($class_path)) {
            echo 'Unable to load file: ' . $class_path . "\n";
            exit;
        }

        require_once $class_path;
    }
}
