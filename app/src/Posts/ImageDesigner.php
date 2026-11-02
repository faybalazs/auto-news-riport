<?php
declare(strict_types=1);

namespace Xperion\Posts;

use Xperion\Claude\ClaudeClient;
use Xperion\Claude\ClaudeException;

/**
 * A poszt képe: a Claude a jóváhagyott minta (docs/terv/poszt-kep-minta.png) elrendezései közül választ,
 * és megírja a kép szövegeit; a rajzot a program készíti rögzített sablonokból (ImageTemplates), így a kép
 * mindig a minta minőségében készül. A Lato betűt megjelenítéskor a program ágyazza be (SvgFonts).
 */
final class ImageDesigner
{
    public const GUIDE = <<<'TXT'
A kép a jóváhagyott minta arculatát követi (sötétkék rácsos háttér, cián kiemelés). Te csak az elrendezést választod és a szövegeket írod; a rajzot a program készíti.
Elrendezések (layout):
- osszehasonlitas: „ILLUSZTRATÍV PÉLDA” – két kártya: mért → valós érték (example: measured_label, measured_value, real_label, real_value, note: egy rövid magyarázó sor), alatta 3–4 lépéses folyamatlánc (steps, pl. Adat, Információ, Tudás, Döntés). CSAK akkor, ha a megadott adatokban van mért és valós érték; számot ne találj ki.
- pontok: három pont (points: title legfeljebb kb. 35 karakter, text legfeljebb kb. 90 karakter).
- kulcsszam: egy nagy szám (number, pl. „-33%”, „2,4 perc”), number_label (mire vonatkozik), number_note (forrás vagy magyarázat egy mondatban). CSAK a forrásban szereplő számmal.
- valogatas: havi válogatás 2–4 cikkből (items: source = a forrás neve, title = a cikk rövid magyar címe).
- kerdes: erős kérdés vagy állítás nagy betűvel, alatta 2–4 lépéses folyamatlánc (steps), ha illik; különben üres steps.
Szövegek:
- eyebrow: rövid szakterület-címke (pl. Visitor analytics, Sorkezelés, Ügyfélélmény, Digital signage, Havi válogatás).
- title: erős kérdés vagy állítás a poszt lényegéből, legfeljebb kb. 60 karakter; a sortörést te add meg „\n”-nel értelmes egységekre (pl. „Mennyit ér egy\npontatlan látogatószám?”).
- subtitle: egy rövid mondat, legfeljebb kb. 60 karakter.
- tagline: a lábléc rövid mondata, legfeljebb kb. 45 karakter (pl. „Pontos mérés a fizikai ügyfélterekben”).
- Minden szöveg magyar, helyes ékezetekkel; a nem használt mezők üresek (üres szöveg vagy üres lista).
Válassz változatosan: ha a legutóbbi posztok képe ugyanazzal az elrendezéssel készült, és van más, a tartalomhoz illő, azt válaszd.
TXT;

    public function __construct(private ClaudeClient $claude)
    {
    }

    /**
     * @param array<string,mixed> $post a poszt mezői (topic, opening, highlights, image{title, subtitle, measured, real}, articles)
     * @param list<string> $recentLayouts a legutóbbi képek elrendezései
     * @return array{svg:string, spec:array<string,mixed>}
     */
    public function design(string $clientName, string $website, string $imageRules, array $post, string $request, string $model, array $recentLayouts = []): array
    {
        $system = "A(z) {$clientName} LinkedIn-posztjához készülő kép tartalmát tervezed.\n\n" . self::GUIDE . "\n\n"
            . 'A Posztszabályok képre vonatkozó sorai: ' . "\n" . ($imageRules !== '' ? $imageRules : '- (nincs külön szabály)');
        $user = json_encode(['poszt' => $post, 'legutóbbi elrendezések' => $recentLayouts, 'kérés a képhez' => $request !== '' ? $request : '(nincs külön kérés)'], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        $str = ['type' => 'string'];
        $schema = ['type' => 'object', 'additionalProperties' => false,
            'required' => ['layout', 'eyebrow', 'title', 'subtitle', 'example', 'steps', 'points', 'number', 'number_label', 'number_note', 'items', 'tagline'],
            'properties' => [
                'layout' => ['type' => 'string', 'enum' => ImageTemplates::LAYOUTS], 'eyebrow' => $str, 'title' => $str, 'subtitle' => $str,
                'example' => ['type' => 'object', 'additionalProperties' => false, 'required' => ['measured_label', 'measured_value', 'real_label', 'real_value', 'note'],
                    'properties' => ['measured_label' => $str, 'measured_value' => $str, 'real_label' => $str, 'real_value' => $str, 'note' => $str]],
                'steps' => ['type' => 'array', 'items' => $str],
                'points' => ['type' => 'array', 'items' => ['type' => 'object', 'additionalProperties' => false, 'required' => ['title', 'text'], 'properties' => ['title' => $str, 'text' => $str]]],
                'number' => $str, 'number_label' => $str, 'number_note' => $str,
                'items' => ['type' => 'array', 'items' => ['type' => 'object', 'additionalProperties' => false, 'required' => ['source', 'title'], 'properties' => ['source' => $str, 'title' => $str]]],
                'tagline' => $str,
            ]];
        $spec = $this->claude->json($model, $system, (string) $user, $schema, 'medium', 8000);
        if (trim((string) ($spec['title'] ?? '')) === '') {
            throw new ClaudeException('A Claude nem adott címet a képhez. Próbálja újra az „Új kép” gombbal.');
        }
        $svg = SvgSanitizer::clean(ImageTemplates::render($spec, $website));
        if ($svg === null) {
            throw new ClaudeException('A kép nem állítható elő. Próbálja újra az „Új kép” gombbal.');
        }
        return ['svg' => $svg, 'spec' => $spec];
    }
}
