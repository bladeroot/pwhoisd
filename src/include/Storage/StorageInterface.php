<?php declare(strict_types=1);
/**
 * HSDN PHP Whois Server Daemon
 *
 * @author      HSDN Team
 * @copyright   (c) 2015, Information Networks Ltd.
 * @link        http://www.hsdn.org
 */

namespace pWhoisd\Storage;

/**
 * Storage Interface.
 */
interface StorageInterface
{
    /**
     * Gets storage search result.
     *
     * @param string $request Search string
     */
    public function get(string $request): array|bool;
}
