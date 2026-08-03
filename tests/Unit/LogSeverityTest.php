<?php declare(strict_types=1);
/**
 * @author bladeroot@gmail.com, 2026
 */

namespace PWhoisdTest\Unit;

use pWhoisd\Application;
use pWhoisd\Log;
use PWhoisdTest\Support\ArrayConfig;
use PHPUnit\Framework\TestCase;

/**
 * Regression test for a bug found via production logs: LOG_SEVERITY=warning
 * was expected to hide info-level "Connected"/"Request Recieved"/etc. lines
 * from console output, but Log::add() printed every non-debug message to
 * the console unconditionally, regardless of the configured severity - only
 * the log *file* (when LOG_FILE is set, which it isn't in the Docker
 * deployments) honored it.
 */
final class LogSeverityTest extends TestCase
{
    public function testWarningSeverityHidesInfoButShowsWarningAndError(): void
    {
        $log = $this->logWithSeverity(Log::warning);

        $this->assertSame('', $this->captureOutput(fn () => $log->info('should be hidden')));
        $this->assertStringContainsString('should be shown', $this->captureOutput(fn () => $log->warning('should be shown')));
        $this->assertStringContainsString('should be shown too', $this->captureOutput(fn () => $log->error('should be shown too')));
    }

    public function testDefaultErrorSeverityHidesInfoAndWarning(): void
    {
        $log = $this->logWithSeverity(Log::error);

        $this->assertSame('', $this->captureOutput(fn () => $log->info('hidden')));
        $this->assertSame('', $this->captureOutput(fn () => $log->warning('hidden')));
        $this->assertStringContainsString('shown', $this->captureOutput(fn () => $log->error('shown')));
    }

    public function testDebugSeverityShowsEverythingIncludingDebug(): void
    {
        $log = $this->logWithSeverity(Log::debug);

        $this->assertStringContainsString('shown', $this->captureOutput(fn () => $log->info('shown')));
        $this->assertStringContainsString('shown', $this->captureOutput(fn () => $log->debug('shown')));
    }

    private function logWithSeverity(int $severity): Log
    {
        Application::$config = new ArrayConfig([
            'logging' => ['severity' => $severity, 'file' => false],
        ]);

        return new Log();
    }

    private function captureOutput(callable $fn): string
    {
        ob_start();
        $fn();

        return ob_get_clean();
    }
}
