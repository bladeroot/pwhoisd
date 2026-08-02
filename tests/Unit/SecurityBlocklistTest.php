<?php declare(strict_types=1);

namespace PWhoisdTest\Unit;

use pWhoisd\Application;
use pWhoisd\Blocklist;
use pWhoisd\Security;
use PWhoisdTest\Support\ArrayConfig;
use PWhoisdTest\Support\ClientFactory;
use PHPUnit\Framework\TestCase;

final class SecurityBlocklistTest extends TestCase
{
    public function testBlockedClientIsDroppedBeforeConfiguredRulesRun(): void
    {
        // A permissive 'allow' rule would let everyone through if the
        // blocklist check didn't short-circuit process_rules() first.
        Application::$config = new ArrayConfig([
            'security' => ['rules' => [['allow']]],
        ]);
        Application::$blocklist = $this->blocklistThatBlocks('198.51.100.1');

        $security = new Security();
        $security->initialize(ClientFactory::withAddress('198.51.100.1'));

        $this->assertSame('drop', $security->get_action());
        $this->assertNull($security->get_message());
    }

    public function testNonBlockedClientStillFallsThroughToConfiguredRules(): void
    {
        Application::$config = new ArrayConfig([
            'security' => ['rules' => [['allow']]],
        ]);
        Application::$blocklist = $this->blocklistThatBlocks('198.51.100.1');

        $security = new Security();
        $security->initialize(ClientFactory::withAddress('198.51.100.99'));

        $this->assertSame('allow', $security->get_action());
    }

    private function blocklistThatBlocks(string $blockedIp): Blocklist
    {
        return new class ($blockedIp) extends Blocklist {
            public function __construct(private readonly string $blockedIp)
            {
                // Intentionally skips parent::__construct() - no config or
                // filesystem access needed for this fixed-answer double.
            }

            public function is_blocked(string $ip): bool
            {
                return $ip === $this->blockedIp;
            }

            public function block(string $ip): void
            {
                throw new \LogicException('not expected to be called from Security');
            }
        };
    }
}
