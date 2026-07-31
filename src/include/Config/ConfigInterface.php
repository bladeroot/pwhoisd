<?php declare(strict_types=1);
/**
 * HSDN PHP Whois Server Daemon
 *
 * @author      HSDN Team
 * @copyright   (c) 2015, Information Networks Ltd.
 * @link        http://www.hsdn.org
 */

namespace pWhoisd\Config;

use ArrayAccess;

/**
 * Config Interface.
 */
interface ConfigInterface extends ArrayAccess
{
    /**
     * Set one or more configuration values.
     *
     * @param string|array $key   Config key value or array of keys and values.
     * @param mixed        $value Configuration value or null if $key is given an array.
     */
    public function set(string|array $key, mixed $value = null): self;

    /**
     * Check if a configuration value is set.
     *
     * @param string|null $key Configuration key to check. If null, checks whether any value is set at all.
     */
    public function has(?string $key = null): bool;

    /**
     * Get a configuration value.
     *
     * @throws \RuntimeException If the specified key is not found and no default is given
     * @param string|null $key     Configuration key whose value to get.
     * @param mixed       $default Default value if the searched key is not found.
     */
    public function get(?string $key = null, mixed $default = null): mixed;

    /**
     * Remove a configuration value.
     */
    public function remove(string $key): self;

    /**
     * Clear all configuration values.
     */
    public function clear(): void;
}
