<?php declare(strict_types=1);
/**
 * @author bladeroot@gmail.com, 2026
 */

namespace PWhoisdTest\Unit;

use pWhoisd\Application;
use pWhoisd\Client;
use PWhoisdTest\Support\ArrayConfig;
use PWhoisdTest\Support\ClientFactory;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

final class ClientRequestValidationTest extends TestCase
{
    /**
     * @dataProvider validRequests
     */
    public function testValidRequestsPassValidation(string $request): void
    {
        $this->assertTrue($this->invokeIsValidRequest($request));
    }

    public static function validRequests(): array
    {
        return [
            'empty (blank line ping)' => [''],
            'plain domain'            => ['example.com'],
            'hyphenated domain'       => ['proton-pro.com'],
            'help flag'               => ['help'],
            'domain with flag'        => ['example.com -h'],
        ];
    }

    /**
     * @dataProvider invalidRequests
     */
    public function testInvalidRequestsFailValidation(string $request): void
    {
        $this->assertFalse($this->invokeIsValidRequest($request));
    }

    public static function invalidRequests(): array
    {
        return [
            'high-byte binary noise' => ["\xBE\xC9\xC7\x65\x6F\x81\x8A\x4A\xDC\x8E"],
            'control character'      => ["example.com\x01"],
            'null byte'               => ["example\x00.com"],
            'esc / ansi sequence'     => ["\x1B[31mexample.com\x1B[0m"],
        ];
    }

    public function testInvalidRequestMessageDefaultsWhenConfigMissing(): void
    {
        Application::$config = new ArrayConfig([]);

        $this->assertSame(
            'Invalid request format. This connection has been blocked.',
            $this->invokeInvalidRequestMessage()
        );
    }

    public function testInvalidRequestMessageJoinsConfiguredLinesArray(): void
    {
        Application::$config = new ArrayConfig([
            'messages' => [
                'invalid_data' => ['Line one.', 'Line two.'],
            ],
        ]);

        $this->assertSame("Line one.\nLine two.", $this->invokeInvalidRequestMessage());
    }

    private function invokeIsValidRequest(string $request): bool
    {
        $client = ClientFactory::withAddress('203.0.113.1');
        $method = (new ReflectionClass(Client::class))->getMethod('is_valid_request');

        return $method->invoke($client, $request);
    }

    private function invokeInvalidRequestMessage(): string
    {
        $client = ClientFactory::withAddress('203.0.113.1');
        $method = (new ReflectionClass(Client::class))->getMethod('invalid_request_message');

        return $method->invoke($client);
    }
}
