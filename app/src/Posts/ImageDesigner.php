<?php
declare(strict_types=1);

namespace Xperion\Posts;

use Xperion\Claude\ClaudeClient;
use Xperion\Claude\ClaudeException;

/**
 * A poszt egyedi képe: a Claude 1200×1200-as SVG-grafikát tervez a poszt témájához, a jóváhagyott
 * mintakép (app/resources/poszt-kep-minta.svg, docs/terv/poszt-kep-minta.png) minőségében és arculatával,
 * a Posztszabályok képre vonatkozó soraival. A Lato betűt megjelenítéskor a program ágyazza be (SvgFonts).
 */
final class ImageDesigner
{
    public const DESIGN_SYSTEM = <<<'TXT'
ARCULAT (a jóváhagyott mintakép alapján – ezt a minőséget és stílust kell hozni):
- Vászon: 1200×1200. Háttér: függőleges színátmenet #0A1630 (fent) → #13295A (lent), rajta finom 60 px-es rácsháló (#1E3566, átlátszóság 0,45).
- Margó: bal és jobb oldalon 90 px; semmi nem lóghat 90 px-en belülre (a szöveg sem).
- Betű: font-family="Lato, 'Helvetica Neue', Helvetica, Arial, sans-serif" (a Latót a program beágyazza). Szélességbecslés Latóval: félkövér sor szélessége ≈ karakterszám × 0,55 × betűméret, normál ≈ × 0,50. Egy sor legfeljebb 1020 px széles lehet; ha hosszabb, törd tspan-sorokra, vagy kisebb betűt használj.
- Színek: kiemelés #00A6EA (cián), fehér #FFFFFF a fő szövegnek, másodlagos szöveg #9FB3D1 és #B7C6DE, sötét kártya #15305E vagy #12295A, cián kártyán sötét szöveg #0A1A3A, elválasztó vonal #2A3F6B.
- Hierarchia, fentről lefelé:
  1. Kategóriacímke: NAGYBETŰS, 26 px, 700-as vastagság, cián, y≈124; alatta 70×6 px-es cián vonal (y≈140). Rövid szakterület, pl. VISITOR ANALYTICS, SORKEZELÉS, ÜGYFÉLÉLMÉNY, DIGITAL SIGNAGE, HAVI VÁLOGATÁS.
  2. Cím: 72–80 px, 700, fehér, legfeljebb 2–3 sor, sorköz ≈ 1,25 × betűméret (első sor alapvonala y≈265). Erős, kérdő vagy állító mondat a poszt lényegéből.
  3. Alcím: 34–38 px, normál, #9FB3D1, egy sor.
  4. Fő vizuális blokk (y≈500–1020), az alábbi elrendezések közül a tartalomhoz illővel.
  5. Lábléc: vízszintes elválasztó vonal y≈1070 (90 → 1110); bal oldalt rövid, a témához illő mondat (28 px, #9FB3D1), jobb oldalt a webhely (32 px, 700, fehér, text-anchor="end", x=1110), alapvonal y≈1126.
- Elemek: lekerekített kártyák (rx 24), tablettaformájú címkék (rx = magasság/2, 2–3 px-es cián körvonal), egyszerű nyilak (vonal + kis háromszög), sok levegő, nincs zsúfoltság, nincs árnyékhalmozás, nincs clipart.
ELRENDEZÉSEK (válassz a tartalomhoz; ne mindig ugyanazt):
  A) „Mért → valós” számpélda: „ILLUSZTRATÍV PÉLDA” tablettacímke, két 440×230-as kártya (bal sötét: halványabb szám, jobb cián: sötét, nagyobb hangsúlyú szám), köztük fehér nyíl, alatta egysoros magyarázat; alul folyamatlánc tablettákból (pl. Adat → Információ → Tudás → Döntés, az utolsó kitöltve ciánnal). Csak akkor, ha a megadott adatokban van mért és valós érték.
  B) Egy nagy kulcsszám vagy állítás: egy széles kártya óriás számmal (140–180 px) és rövid magyarázattal; csak ha a szám a forrásban szerepel.
  C) Három pont: három egymás alatti sor vagy kártya, mindegyik elején kis, egyszerű geometriai ikon (kör, sávdiagram, térképjel, emberalak vonalakból), rövid félkövér címmel és egy sor magyarázattal.
  D) Folyamat vagy összefüggés: 3–4 tabletta vagy kör nyilakkal összekötve, egyszerű diagram (pl. sávdiagram, vonaldiagram, ügyféltér alaprajz vonalakból).
  E) Havi szakmai válogatás: „HAVI VÁLOGATÁS” címke, a cikkek 2–4 sora számozott kártyákon (forrás + rövid, lefordított cím), egységes stílusban.
TXT;

    public function __construct(private ClaudeClient $claude)
    {
    }

    /**
     * @param array<string,mixed> $post a poszt mezői (topic, opening, highlights, image{title, subtitle, measured, real})
     */
    public function design(string $clientName, string $website, string $imageRules, array $post, string $request, string $model): string
    {
        $sample = (string) @file_get_contents(dirname(__DIR__, 2) . '/resources/poszt-kep-minta.svg');
        $system = "Senior grafikus vagy: a(z) {$clientName} LinkedIn-posztjához tervezel egyetlen, igényes, 1200×1200-as SVG-képet.\n\n"
            . self::DESIGN_SYSTEM . "\n\n"
            . 'A Posztszabályok képre vonatkozó sorai (ezek is kötelezők):' . "\n" . ($imageRules !== '' ? $imageRules : '- (nincs külön szabály)') . "\n\n"
            . "TECHNIKAI SZABÁLYOK:\n"
            . "- Gyökérelem: <svg xmlns=\"http://www.w3.org/2000/svg\" viewBox=\"0 0 1200 1200\" width=\"1200\" height=\"1200\">. Csak SVG-alakzatok, színátmenetek, minták (pattern), szöveg; kép (image), külső hivatkozás, szkript, foreignObject és <style> nincs. Stílus csak attribútumként.\n"
            . "- A szövegek magyarok (a kategóriacímke lehet angol szakkifejezés), helyes ékezetekkel. A webhely: {$website}.\n"
            . "- Számot, nevet, tényt ne találj ki: csak a megadott adatokból dolgozz. Számpélda csak „Illusztratív példa” jelöléssel, és csak ha meg van adva.\n"
            . "- Ellenőrizd a végén: minden szöveg a 90–1110 px-es sávon belül van, nincs átfedés, a kártyákba írt szöveg belefér.\n\n"
            . "MINTA (a jóváhagyott kép SVG-je; az arányokat, a méreteket és a kidolgozottságot kövesd, a tartalmát ne másold):\n" . $sample;
        $user = json_encode(['poszt' => array_intersect_key($post, array_flip(['topic', 'opening', 'highlights', 'image'])), 'kérés a képhez' => $request !== '' ? $request : '(nincs külön kérés)'], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        $res = $this->claude->json($model, $system, (string) $user, ['type' => 'object', 'properties' => ['svg' => ['type' => 'string']], 'required' => ['svg'], 'additionalProperties' => false], 'high', 20000);
        $svg = SvgSanitizer::clean((string) ($res['svg'] ?? ''));
        if ($svg === null) {
            throw new ClaudeException('A Claude által tervezett kép nem érvényes SVG. Próbálja újra az „Új kép” paranccsal.');
        }
        return $svg;
    }
}
