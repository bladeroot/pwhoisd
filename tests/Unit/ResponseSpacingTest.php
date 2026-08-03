<?php declare(strict_types=1);

namespace PWhoisdTest\Unit;

use pWhoisd\Application;
use pWhoisd\Request;
use PWhoisdTest\Support\ArrayConfig;
use PWhoisdTest\Support\ClientFactory;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * Regression test for a real-world display bug: with spacing => false,
 * Response::process_response() concatenated "Label:" and the value with no
 * separator at all ("Server Name:NS1.EXAMPLE.COM"), unlike spacing => true
 * which always has at least one space (plus column-alignment padding).
 * Real WHOIS output is never "Label:value" - only whether it's column
 * aligned should be optional, not whether there's a space at all.
 */
final class ResponseSpacingTest extends TestCase
{
    public function testFieldsAreSpaceSeparatedWithSpacingDisabled(): void
    {
        $response = $this->render(spacing: false, fields: [
            ['Server Name', 'HostName'],
            ['IP Address', 'HostIP'],
        ]);

        $this->assertStringContainsString('Server Name: NS1.EXAMPLE.COM', $response);
        $this->assertStringContainsString('IP Address: 192.0.2.1', $response);
        $this->assertStringNotContainsString('Server Name:NS1.EXAMPLE.COM', $response);
    }

    public function testFieldsAreColumnAlignedWithSpacingEnabled(): void
    {
        $response = $this->render(spacing: true, fields: [
            ['Server Name', 'HostName'],
            ['IP Address', 'HostIP'],
        ]);

        // "Server Name:" (12 chars) and "IP Address:" (11 chars) - the
        // shorter label gets one extra padding space to align the values.
        $this->assertStringContainsString('Server Name: NS1.EXAMPLE.COM', $response);
        $this->assertStringContainsString('IP Address:  192.0.2.1', $response);
    }

    /**
     * @param array<int, array{0: string, 1: string}> $fields
     */
    private function render(bool $spacing, array $fields): string
    {
        // ArrayConfig::set() treats an empty array as "no keys to set" (see
        // ConfigAbstract::containsOnlyStringKeys()'s vacuous true on []), so
        // 'messages' would never exist at all - a harmless placeholder key
        // avoids that, since process_response_macro() unconditionally reads
        // Application::$config->get('messages') with no default.
        Application::$config = new ArrayConfig(['messages' => ['placeholder' => '']]);

        $request = (new ReflectionClass(Request::class))->newInstanceWithoutConstructor();
        $reflection = new ReflectionClass($request);

        $reflection->getProperty('client')->setValue($request, ClientFactory::withAddress('203.0.113.1'));

        $dataSection = $reflection->getProperty('data_section');
        $dataSection->setValue($request, [
            'spacing'    => $spacing,
            'hide_empty' => true,
            'fields'     => $fields,
        ]);

        $responseArray = $reflection->getProperty('response_array');
        $responseArray->setValue($request, [
            'HostName' => 'NS1.EXAMPLE.COM',
            'HostIP'   => '192.0.2.1',
        ]);

        $reflection->getMethod('process_response')->invoke($request);

        return $request->get_response();
    }
}
