<?php
declare(strict_types=1);

namespace Xperion\Posts;

use Xperion\Claude\ClaudeClient;
use Xperion\Claude\ClaudeException;

/**
 * A poszt egyedi képe: a Claude 1200×1200-as SVG-grafikát tervez a poszt témájához,
 * a Posztszabályok képre vonatkozó soraival és a márka színeivel. A felület PNG-t készít belőle.
 */
final class ImageDesigner
{
    public function __construct(private ClaudeClient $claude)
    {
    }

    /**
     * @param array<string,mixed> $post a poszt mezői (topic, opening, highlights, image{title, subtitle, measured, real})
     */
    public function design(string $clientName, string $website, string $imageRules, array $post, string $request, string $model): string
    {
        $system = "Grafikus vagy: a(z) {$clientName} LinkedIn-posztjához tervezel egyetlen, igényes, 1200×1200-as SVG-képet.\n"
            . "Márka és stílus (a Posztszabályok képre vonatkozó sorai, ezek az irányadók):\n" . ($imageRules !== '' ? $imageRules : '- sötétkék háttér, #2D388A → #00AEEF színátmenet, letisztult, modern, vállalati') . "\n"
            . "Szabályok:\n"
            . "- Gyökérelem: <svg xmlns=\"http://www.w3.org/2000/svg\" viewBox=\"0 0 1200 1200\" width=\"1200\" height=\"1200\">. Csak SVG-alakzatok, színátmenetek, szűrők és szöveg; kép, külső hivatkozás, szkript, foreignObject és CSS-import nincs.\n"
            . "- Betű: font-family=\"Arial, Helvetica, sans-serif\". Az SVG nem tör sort magától: a hosszú szöveget te tördeld <tspan x=… dy=…> sorokra; a cím legfeljebb 3–4 sor, egy sor legfeljebb kb. 18 karakter 84 px-es betűnél. Minden szöveg legalább 80 px-re legyen a szélektől, és ne lógjon ki.\n"
            . "- Tartalom: a kép címe és alcíme (a megadott szöveggel, magyarul), a témához illő absztrakt vagy ikonszerű illusztráció (pl. ügyféltér, sorban állás, diagram, térkép, adatpontok), alul a webhely: {$website}.\n"
            . "- Ha van „mért → valós” számpélda, jól olvasható kiemelésként jelenjen meg, „Illusztratív példa” címkével. Számot ne találj ki.\n"
            . '- Legyen professzionális és egyedi, ne zsúfolt; erős kontraszt, a szöveg mindenhol jól olvasható.';
        $user = json_encode(['poszt' => array_intersect_key($post, array_flip(['topic', 'opening', 'highlights', 'image'])), 'kérés' => $request], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        $res = $this->claude->json($model, $system, (string) $user, ['type' => 'object', 'properties' => ['svg' => ['type' => 'string']], 'required' => ['svg'], 'additionalProperties' => false], 'medium', 16000);
        $svg = SvgSanitizer::clean((string) ($res['svg'] ?? ''));
        if ($svg === null) {
            throw new ClaudeException('A Claude által tervezett kép nem érvényes SVG. Próbálja újra az „Új kép” paranccsal.');
        }
        return $svg;
    }
}
