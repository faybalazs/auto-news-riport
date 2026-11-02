<?php
declare(strict_types=1);

namespace Xperion\Tests;

use Xperion\Http\HttpClient;
use Xperion\Http\HttpResponse;

/**
 * Helyettesítő Claude API: az értékelő kérésre minden kapott indexre értékelést ad
 * (a cím alapján: „IPO” → Nem javasolt, „bank” → Erősen javasolt, egyébként Javasolt),
 * az összefoglalóra két sort. Rögzíti a kéréseket.
 */
final class FakeClaude implements HttpClient
{
    /** @var list<array{headers:array<string,string>, body:array<string,mixed>}> */
    public array $requests = [];
    public ?string $stopReason = null;
    public bool $dropLast = false;

    public function request(string $method, string $url, array $headers = [], ?string $body = null): HttpResponse
    {
        $b = json_decode((string) $body, true);
        $this->requests[] = ['headers' => $headers, 'body' => $b];
        $props = $b['output_config']['format']['schema']['properties'] ?? [];
        if (isset($props['items'])) {
            preg_match('/\[.*\]/s', $b['messages'][0]['content'], $m);
            $items = json_decode($m[0], true);
            $out = [];
            foreach ($items as $it) {
                $rec = str_contains($it['title'], 'IPO') ? 'Nem javasolt' : (stripos($it['title'], 'bank') !== false ? 'Erősen javasolt' : 'Javasolt');
                $out[] = ['index' => $it['index'], 'desc' => 'Magyar leírás: ' . $it['title'], 'recommendation' => $rec, 'reason' => 'Mert releváns.'];
            }
            if ($this->dropLast) {
                array_pop($out);
            }
            $text = json_encode(['items' => $out], JSON_UNESCAPED_UNICODE);
        } elseif (isset($props['hu'])) {
            preg_match('/^Cím: (.*)$/m', $b['messages'][0]['content'], $m);
            $text = json_encode(['hu' => ['title' => 'HU: ' . ($m[1] ?? ''), 'summary' => 'Magyar ajánló.'], 'en' => ['title' => 'EN: ' . ($m[1] ?? ''), 'summary' => 'English teaser.']], JSON_UNESCAPED_UNICODE);
        } elseif (isset($props['subject'])) {
            $text = json_encode(['subject' => 'Sajtófigyelés – 2026. október', 'body' => "Kedves Attila!\n\nElkészült az októberi lista:\nhttps://app.test/index.php?p=cikkek&honap=2610\n\n- Új forrás: Acrelec, egy cikkel.\n- Cikkek: 3\n\nÜdvözlettel: az Auris Dynamics csapata"], JSON_UNESCAPED_UNICODE);
        } else {
            $text = json_encode(['rows' => [['label' => 'Cikkek száma', 'value' => '3'], ['label' => 'Fő téma', 'value' => 'várakozás']], 'details' => 'Októberben a várakozás mérése volt a fő téma.'], JSON_UNESCAPED_UNICODE);
        }
        return new HttpResponse(200, (string) json_encode([
            'content' => [['type' => 'thinking', 'thinking' => ''], ['type' => 'text', 'text' => $text]],
            'stop_reason' => $this->stopReason ?? 'end_turn',
            'stop_details' => $this->stopReason === 'refusal' ? ['type' => 'refusal', 'category' => 'cyber'] : null,
            'usage' => ['input_tokens' => 1000, 'output_tokens' => 200, 'cache_read_input_tokens' => 0],
        ]));
    }
}
