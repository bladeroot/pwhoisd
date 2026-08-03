<?php declare(strict_types=1);
/**
 * @author bladeroot@gmail.com, 2026
 */

namespace PWhoisdTest\Unit;

use pWhoisd\Application;
use pWhoisd\Blocklist;
use PWhoisdTest\Support\ArrayConfig;
use PHPUnit\Framework\TestCase;

final class BlocklistTest extends TestCase
{
    private string $path;

    protected function setUp(): void
    {
        $this->path = sys_get_temp_dir() . '/pwhoisd-test-blocklist-' . uniqid('', true) . '.json';
    }

    protected function tearDown(): void
    {
        @unlink($this->path);
    }

    private function blocklist(int $ttl = 36000, bool $enabled = true): Blocklist
    {
        Application::$config = new ArrayConfig([
            'security' => [
                'blocklist' => [
                    'enabled' => $enabled,
                    'ttl'     => $ttl,
                    'path'    => $this->path,
                ],
            ],
        ]);

        return new Blocklist();
    }

    public function testUnknownIpIsNotBlocked(): void
    {
        $this->assertFalse($this->blocklist()->is_blocked('203.0.113.1'));
    }

    public function testBlockedIpIsReportedBlocked(): void
    {
        $blocklist = $this->blocklist();

        $blocklist->block('203.0.113.1');

        $this->assertTrue($blocklist->is_blocked('203.0.113.1'));
    }

    public function testBlockingOneIpDoesNotAffectAnother(): void
    {
        $blocklist = $this->blocklist();

        $blocklist->block('203.0.113.1');

        $this->assertFalse($blocklist->is_blocked('203.0.113.2'));
    }

    public function testExpiredBlockIsTreatedAsNotBlocked(): void
    {
        // A negative TTL puts the expiry timestamp in the past immediately,
        // exercising expiry without sleeping in the test.
        $blocklist = $this->blocklist(ttl: -5);

        $blocklist->block('203.0.113.1');

        $this->assertFalse($blocklist->is_blocked('203.0.113.1'));
    }

    public function testDisabledBlocklistNeverBlocksEvenAfterBlockCall(): void
    {
        $blocklist = $this->blocklist(enabled: false);

        $blocklist->block('203.0.113.1');

        $this->assertFalse($blocklist->is_blocked('203.0.113.1'));
    }

    public function testEmptyIpIsNeverBlocked(): void
    {
        $blocklist = $this->blocklist();

        $blocklist->block('');

        $this->assertFalse($blocklist->is_blocked(''));
    }

    public function testBlockIsVisibleToAFreshInstanceReadingTheSameFile(): void
    {
        // Simulates the real cross-process case: one forked worker calls
        // block(), a later connection's Security::initialize() (running in
        // a different process, hence a different Blocklist instance) must
        // see it via the shared file.
        $this->blocklist()->block('203.0.113.1');

        $this->assertTrue($this->blocklist()->is_blocked('203.0.113.1'));
    }

    public function testBlockingTwoIpsSeparatelyKeepsBothBlocked(): void
    {
        // Regression guard for the read-modify-write file update: a second
        // block() call must not clobber the first IP's entry.
        $first = $this->blocklist();
        $first->block('203.0.113.1');

        $second = $this->blocklist();
        $second->block('203.0.113.2');

        $this->assertTrue($first->is_blocked('203.0.113.1'));
        $this->assertTrue($first->is_blocked('203.0.113.2'));
    }
}
