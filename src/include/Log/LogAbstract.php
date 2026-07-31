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
 * Abstract Log implementing LogInterface.
 */
abstract class LogAbstract implements LogInterface
{
    /*
     * @const int Error messages level
     */
    const error = 1;

    /*
     * @const int Warning messages level
     */
    const warning = 2;

    /*
     * @const int Info messages level
     */
    const info = 3;

    /*
     * @const int Debug messages level
     */
    const debug = 4;

    /*
     * @var int|false Logging severity
     */
    protected int|false $severity = false;

    /*
     * @var string|false Logging file path
     */
    protected string|false $file = false;

    /*
     * @var array Severity names
     */
    protected array $severities = [
        self::debug   => ['debug', 'cyan'],
        self::info    => ['info', 'green'],
        self::warning => ['warning', 'yellow'],
        self::error   => ['error', 'red'],
    ];

    /**
     * {@inheritdoc}
     */
    public function debug(string $message): void
    {
        $this->add($this->get_calling_class().': '.$message, self::debug);
    }

    /**
     * {@inheritdoc}
     */
    public function info(string $message): void
    {
        $this->add($message, self::info);
    }

    /**
     * {@inheritdoc}
     */
    public function warning(string $message): void
    {
        $this->add($message, self::warning);
    }

    /**
     * {@inheritdoc}
     */
    public function error(string $message): void
    {
        $this->add($message, self::error);
    }
}
