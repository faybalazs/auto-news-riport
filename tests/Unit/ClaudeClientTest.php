<?php
declare(strict_types=1);

namespace Xperion\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Xperion\Claude\ClaudeClient;
use Xperion\Claude\ClaudeException;
use Xperion\Tests\FakeClaude;

final class ClaudeClientTest extends TestCase
{
    private const SCHEMA = ['type' => 'object', 'properties' => ['rows' => ['type' => 'array', 'items' => ['type' => 'string']]], 'required' => ['rows'], 'additionalProperties' => false];

    public function testRequestShapeAndUsage(): void
    {
        $fake = new FakeClaude();
        $c = new ClaudeClient($fake, 'sk-test');
        $out = $c->json('claude-haiku-5-5', 'rendszer', 'kérés', self::SCHEMA, 'low');
        self::assertSame('Cikkek száma', $out['rows'][0]['label']);
        $r = $fake->requests[0];
        self::assertSame('2026-10-21', $r['headers']['anthropic-version']);
        self::assertSame('sk-test', $r['headers']['x-api-key']);
        self::assertArrayNotHasKey('anthropic-beta', $r['headers'], 'Haikunál nincs szerveroldali tartalék');
        self::assertSame(['effort' => 'low', 'format' => ['type' => 'json_schema', 'schema' => self::SCHEMA]], $r['body']['output_config']);
        self::assertSame(['type' => 'ephemeral'], $r['body']['system'][0]['cache_control']);
        foreach (['temperature', 'top_p', 'top_k', 'thinking'] as $k) {
            self::assertArrayNotHasKey($k, $r['body']);
        }
        self::assertSame(1000, $c->usage['input']);
    }

    public function testSonnetGetsServerSideFallback(): void
    {
        $fake = new FakeClaude();
        (new ClaudeClient($fake, 'k'))->json('claude-sonnet-5-5', 's', 'u', self::SCHEMA, 'medium');
        self::assertSame('server-side-fallback-2026-07-01', $fake->requests[0]['headers']['anthropic-beta']);
        self::assertSame('default', $fake->requests[0]['body']['fallbacks']);
    }

    public function testRefusalAndTruncationAndMissingKey(): void
    {
        $fake = new FakeClaude();
        $fake->stopReason = 'refusal';
        try {
            (new ClaudeClient($fake, 'k'))->json('claude-haiku-5-5', 's', 'u', self::SCHEMA);
            self::fail('elutasítás');
        } catch (ClaudeException $e) {
            self::assertStringContainsString('cyber', $e->getMessage());
        }
        $fake->stopReason = 'max_tokens';
        try {
            (new ClaudeClient($fake, 'k'))->json('claude-haiku-5-5', 's', 'u', self::SCHEMA);
            self::fail('megszakadt');
        } catch (ClaudeException $e) {
            self::assertStringContainsString('max_tokens', $e->getMessage());
        }
        $this->expectExceptionMessage('Nincs beállítva a Claude API-kulcs');
        (new ClaudeClient($fake, ''))->json('claude-haiku-5-5', 's', 'u', self::SCHEMA);
    }
}
