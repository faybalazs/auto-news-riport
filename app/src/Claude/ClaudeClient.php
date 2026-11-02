<?php
declare(strict_types=1);

namespace Xperion\Claude;

use Xperion\Http\HttpClient;

/**
 * Claude Messages API közvetlen HTTP-hívással (a tárhelyen nincs Composer).
 * Strukturált JSON-kimenet (output_config.format), és a válasz ellenőrzése.
 */
final class ClaudeClient
{
    public const API_URL = 'https://api.anthropic.com/v1/messages';
    public const API_VERSION = '2026-10-21';
    /** A szerveroldali tartalék modellt (fallbacks) elfogadó modellek. */
    private const FALLBACK_MODELS = ['claude-sonnet-5-5', 'claude-opus-5-5', 'claude-opus-5', 'claude-fable-5-1'];

    /** @var array{input:int, output:int, cache_read:int} */
    public array $usage = ['input' => 0, 'output' => 0, 'cache_read' => 0];

    public function __construct(private HttpClient $http, private string $apiKey)
    {
    }

    public function hasKey(): bool
    {
        return $this->apiKey !== '';
    }

    /**
     * Egy kérés JSON-sémával korlátozott kimenettel.
     *
     * @param string $system  stabil (gyorsítótárazott) rendszerprompt
     * @param string $user    a kérés változó része
     * @param array<string,mixed> $schema JSON-séma (minden objektumon additionalProperties:false)
     * @return array<string,mixed> a séma szerinti válasz
     */
    public function json(string $model, string $system, string $user, array $schema, string $effort = 'low', int $maxTokens = 16000): array
    {
        if ($this->apiKey === '') {
            throw new ClaudeException('Nincs beállítva a Claude API-kulcs (config.php: claude_api_key).');
        }
        $body = [
            'model' => $model,
            'max_tokens' => $maxTokens,
            'system' => [['type' => 'text', 'text' => $system, 'cache_control' => ['type' => 'ephemeral']]],
            'messages' => [['role' => 'user', 'content' => $user]],
            'output_config' => ['effort' => $effort, 'format' => ['type' => 'json_schema', 'schema' => $schema]],
        ];
        $headers = [
            'Content-Type' => 'application/json',
            'x-api-key' => $this->apiKey,
            'anthropic-version' => self::API_VERSION,
        ];
        if (in_array($model, self::FALLBACK_MODELS, true)) {
            // Ha a biztonsági szűrő elutasítja, a szerver a kategória szerinti tartalék modellel újrapróbálja.
            $body['fallbacks'] = 'default';
            $headers['anthropic-beta'] = 'server-side-fallback-2026-07-01';
        }
        $res = $this->http->request('POST', self::API_URL, $headers, json_encode($body, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));
        if (!$res->ok()) {
            throw new ClaudeException('Claude API: ' . $res->errorMessage());
        }
        $data = $res->json();
        $u = $data['usage'] ?? [];
        $this->usage['input'] += (int) ($u['input_tokens'] ?? 0);
        $this->usage['output'] += (int) ($u['output_tokens'] ?? 0);
        $this->usage['cache_read'] += (int) ($u['cache_read_input_tokens'] ?? 0);

        $stop = (string) ($data['stop_reason'] ?? '');
        if ($stop === 'refusal') {
            $cat = $data['stop_details']['category'] ?? null;
            throw new ClaudeException('A Claude elutasította a kérést' . ($cat ? " ($cat)" : '') . '.');
        }
        if ($stop === 'max_tokens') {
            throw new ClaudeException('A válasz túl hosszú lett, és megszakadt (max_tokens).');
        }
        $text = '';
        foreach ($data['content'] ?? [] as $block) {
            if (($block['type'] ?? '') === 'text') {
                $text .= (string) $block['text'];
            }
        }
        $out = json_decode($text, true);
        if (!is_array($out)) {
            throw new ClaudeException('A Claude válasza nem érvényes JSON.');
        }
        return $out;
    }

    /** Becsült költség USD-ben a választott modell díjával (tájékoztató). */
    public static function estimateUsd(string $model, array $usage): float
    {
        $p = ['claude-haiku-5-5' => [0.10, 0.50], 'claude-sonnet-5-5' => [2.0, 10.0], 'claude-opus-5-5' => [4.0, 20.0]][$model] ?? [2.0, 10.0];
        return ($usage['input'] * $p[0] + $usage['output'] * $p[1]) / 1_000_000;
    }
}
