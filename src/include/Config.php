<?php declare(strict_types=1);
/**
 * HSDN PHP Whois Server Daemon
 *
 * @author      HSDN Team
 * @copyright   (c) 2015, Information Networks Ltd.
 * @link        http://www.hsdn.org
 */

namespace pWhoisd;

use pWhoisd\Config\ConfigAbstract;
use InvalidArgumentException;
use RuntimeException;

/**
 * Config class.
 */
class Config extends ConfigAbstract
{
    /**
     * Returns instance of Config.
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
        $this->load(realpath(Application::$arguments['config']));
    }

    /**
     * Load a configuration file.
     *
     * @throws InvalidArgumentException If $file is not a valid file.
     * @throws RuntimeException         If $file does not return an array.
     * @param string|false $file Path to php file which returns an array.
     */
    public function load(string|false $file): self
    {
        if (empty($file) || !file_exists($file)) {
            throw new InvalidArgumentException('Configuration file must be a valid file.');
        }

        $data = include $file;

        if (!is_array($data)) {
            throw new RuntimeException('Configuration file did not return an array.');
        }

        return $this->set($data);
    }
}
