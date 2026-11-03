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
    /** Az utolsó összefoglaló-kérés felhasználói üzenete (a tesztek ellenőrzik, mit kap a Claude). */
    public string $lastSummaryInput = '';
    /** @var list<array{headers:array<string,string>, body:array<string,mixed>}> */
    public array $requests = [];
    public ?string $stopReason = null;
    public bool $dropLast = false;
    public ?string $svg = null;
    /** @var array<string,mixed>|null */
    public ?array $imageSpec = null;

    public function request(string $method, string $url, array $headers = [], ?string $body = null): HttpResponse
    {
        $b = json_decode((string) $body, true);
        $this->requests[] = ['headers' => $headers, 'body' => $b];
        $props = $b['output_config']['format']['schema']['properties'] ?? [];
        if (isset($props['items']) && !isset($props['layout'])) {
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
        } elseif (isset($props['layout'])) {
            $text = json_encode($this->imageSpec ?? ['layout' => 'osszehasonlitas', 'eyebrow' => 'Visitor analytics', 'title' => "Mennyit ér egy\npontatlan látogatószám?", 'subtitle' => 'Az adat önmagában még nem döntés.',
                'example' => ['measured_label' => 'Mért konverzió', 'measured_value' => '12%', 'real_label' => 'Valós konverzió', 'real_value' => '13,2%', 'note' => 'ha a számláló 10%-kal több látogatót mér'],
                'steps' => ['Adat', 'Információ', 'Tudás', 'Döntés'], 'points' => [], 'number' => '', 'number_label' => '', 'number_note' => '', 'items' => [], 'tagline' => 'Pontos mérés <a&b>'], JSON_UNESCAPED_UNICODE);
        } elseif (isset($props['svg'])) {
            $text = json_encode(['svg' => $this->svg ?? '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1200 1200"><defs><linearGradient id="g"><stop offset="0" stop-color="#2D388A"/><stop offset="1" stop-color="#00AEEF"/></linearGradient></defs><rect width="1200" height="1200" fill="#0F1640"/><circle cx="900" cy="300" r="260" fill="url(#g)" onclick="alert(1)"/><script>alert(1)</script><image href="https://evil.test/x.png"/><text x="96" y="300" font-family="Arial" font-size="84" fill="#fff"><tspan x="96" dy="0">Mért</tspan><tspan x="96" dy="100">várakozás</tspan></text><text x="96" y="1100" fill="#fff">xperion.hu</text></svg>'], JSON_UNESCAPED_UNICODE);
        } elseif (isset($props['opening'])) {
            $c = $b['messages'][0]['content'];
            $text = json_encode(['topic' => str_contains($c, '"kind": "otlet"') ? 'Ötletposzt' : 'Havi válogatás', 'opening' => 'Mit mér egy bankfiók, amikor a várakozást méri?', 'alt_opening_1' => 'Alt 1', 'alt_opening_2' => 'Alt 2',
                'context' => 'Kontextus https://link.test/x a szövegben.', 'highlights' => "– egy\n– kettő", 'xperion_view' => 'Szemszög.', 'closing' => 'Zárás?', 'hashtags' => '#ügyfélélmény #retail',
                'source_link' => 'https://src.test/a', 'note' => '', 'image' => ['title' => 'Mért várakozás', 'subtitle' => 'Alcím', 'measured' => '', 'real' => '']], JSON_UNESCAPED_UNICODE);
        } elseif (isset($props['hu'])) {
            preg_match('/^Cím: (.*)$/m', $b['messages'][0]['content'], $m);
            $text = json_encode(['hu' => ['title' => 'HU: ' . ($m[1] ?? ''), 'summary' => 'Magyar ajánló.'], 'en' => ['title' => 'EN: ' . ($m[1] ?? ''), 'summary' => 'English teaser.']], JSON_UNESCAPED_UNICODE);
        } elseif (isset($props['sections'])) {
            $this->lastSummaryInput = (string) $b['messages'][0]['content'];
            $text = json_encode(['lead' => 'Októberben a várakozás mérése volt a fő téma.', 'sections' => [
                ['title' => 'Trendek', 'points' => ['A bankok mérik a várakozást.', '']],
                ['title' => 'Újdonságok', 'points' => ['A Qmatic új szolgáltatást vezetett be.']],
                ['title' => 'Kitalált csoport', 'points' => ['Nem jelenhet meg.']],
                ['title' => 'Tények, számok', 'points' => ['A Deloitte felmérése szerint a vásárlók 38%-a online kezd.', 'Kettő.', 'Három.', 'Negyedik, levágandó.']],
            ]], JSON_UNESCAPED_UNICODE);
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
