<?php declare(strict_types=1);
/**
 * @author bladeroot@gmail.com, 2026
 */

namespace PWhoisdTest\Support;

use pWhoisd\Log\LogInterface;

/**
 * No-op Log double that records what was logged, so tests can assert on it
 * without Log::__construct()'s dependency on Application::$config or
 * touching the filesystem/stdout.
 */
final class RecordingLog implements LogInterface
{
    /** @var array<int, array{0: string, 1: string}> */
    public array $messages = [];

    public function debug(string $message): void
    {
        $this->messages[] = ['debug', $message];
    }

    public function info(string $message): void
    {
        $this->messages[] = ['info', $message];
    }

    public function warning(string $message): void
    {
        $this->messages[] = ['warning', $message];
    }

    public function error(string $message): void
    {
        $this->messages[] = ['error', $message];
    }

    public function has(string $severity, string $needle): bool
    {
        foreach ($this->messages as [$loggedSeverity, $message]) {
            if ($loggedSeverity === $severity && str_contains($message, $needle)) {
                return true;
            }
        }

        return false;
    }
}
