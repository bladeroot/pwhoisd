<?php declare(strict_types=1);
/**
 * @author bladeroot@gmail.com, 2026
 */

namespace PWhoisdTest\Support;

use pWhoisd\Config;

/**
 * In-memory Config double for tests - extends the concrete Config (rather
 * than just its ConfigInterface) because Application::$config is typed as
 * ?Config, not the interface. Skips Config::__construct()'s dependency on
 * Application::$arguments['config'] and an actual config file on disk.
 */
final class ArrayConfig extends Config
{
    public function __construct(array $data = [])
    {
        $this->set($data);
    }
}
