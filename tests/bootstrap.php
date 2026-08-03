<?php declare(strict_types=1);
/**
 * @author bladeroot@gmail.com, 2026
 */

define('INCLUDE_PATH', dirname(__DIR__) . '/src/include');

require_once INCLUDE_PATH . '/Autoloader.php';

\pWhoisd\Autoloader::register();

require_once __DIR__ . '/../vendor/autoload.php';
