<?php
declare(strict_types=1);

namespace Xperion\Monthly;

use Xperion\Claude\ClaudeClient;

/**
 * Vezetői összefoglaló: rövid bevezető, majd tagolt pontok (Újdonságok, Tények, számok, Trendek), konkrét
 * forrásmegnevezéssel. Cikkstatisztikát nem ír (azt a felület widgetjei mutatják). A régi „Címke: érték” sorok
 * (rows) üresek maradnak; a details mező a bevezető, így a régi megjelenítés is értelmes marad.
 */
final class Summarizer
{
    public const SECTIONS = ['Újdonságok', 'Tények, számok', 'Trendek'];

    /** Csoportonként ikon és ikoncsempe-szín a felülethez. */
    public const ICONS = ['Újdonságok' => ['bulb', 'orange'], 'Tények, számok' => ['chart', 'blue'], 'Trendek' => ['arrow-up', 'green']];

    /** Stílusminta: a felhasználó által jónak ítélt korábbi összefoglaló (2026. szeptember, a Google Sheetből). */
    public const STYLE_SAMPLE = 'A hónap fő témája a látogatószámlálás és a forgalomelemzés üzleti haszna: több cikk foglalkozik a pontatlan számlálás költségeivel, '
        . 'a foglaltsági adatok épületfelügyeleti felhasználásával és a beszerzési, tenderkiírási szempontokkal. Az elemzők közül a Gartner az AI-alapú ügyfélszolgálat '
        . 'korlátait vizsgálta: egy rossz chatbot-élmény után az ügyfelek kevesebb mint harmada próbálkozna újra. A Qmatic új, AI-alapú kiszolgálásminőség-elemző '
        . 'szolgáltatást vett fel a Q-Store kínálatába.';

    public function __construct(private ClaudeClient $claude)
    {
    }

    /**
     * @param list<array<string,mixed>> $items értékelt cikkek (source, title, desc vagy description, recommendation, date / published_on, decision)
     * @return array{rows: list<array{label:string,value:string}>, details:string, sections: list<array{title:string, points: list<string>}>}
     */
    public function summarize(string $clientName, string $monthLabel, array $items, string $model): array
    {
        $system = "Te a(z) {$clientName} havi sajtófigyelésének vezetői összefoglalóját írod, magyarul, a cég vezetőinek.\n"
            . "- lead: 1–2 mondat a hónap fő témájáról. Ne írj cikkszámot, forrásonkénti darabszámot vagy javaslat-statisztikát (pl. „21 cikk”, „14 a Vemco blogján”, „28 nem javasolt”): azt a felület külön mutatja.\n"
            . "- sections: ezek a csoportok, ebben a sorrendben, de csak az, amelyhez van valódi tartalom: „Újdonságok” (új termék, szolgáltatás, bejelentés, partnerség, kinevezés), „Tények, számok” (felmérés, kutatás, mért eredmény, konkrét számmal), „Trendek” (több cikkben visszatérő irány). Csoportonként 1–3 pont, pontonként egy mondat, legfeljebb kb. 35 szó.\n"
            . "- Minden pont konkrét: nevezd meg a forrást vagy a szereplőt, pl. „A Deloitte ünnepi kiskereskedelmi felmérése szerint …”, „A Qmatic … szolgáltatást vett fel a kínálatába”. Ne általánosíts („több cikk foglalkozik …”), ha egy konkrét állítás is megfogalmazható.\n"
            . "- Számot, százalékot, nevet csak akkor írj, ha a cikk címe vagy leírása tartalmazza. Semmit ne találj ki; ha egy csoporthoz nincs ilyen adat, hagyd ki a csoportot.\n"
            . "- Elsősorban az „Erősen javasolt” és a „Javasolt” cikkekből dolgozz; „Nem javasolt” cikket csak akkor említs, ha a hónap képéhez lényeges tény van benne.\n"
            . "- Tárgyilagos, tömör, nem reklámízű. Az egész legyen rövid: kb. 120–180 szó.\n"
            . "Stílusminta (egy korábbi, jónak tartott összefoglaló, még tagolás nélkül): „" . self::STYLE_SAMPLE . '”';
        $list = array_map(static fn ($i) => array_filter([
            'source' => (string) ($i['source'] ?? $i['source_name'] ?? ''),
            'date' => (string) ($i['date'] ?? $i['published_on'] ?? ''),
            'title' => (string) ($i['title'] ?? ''),
            'description' => mb_substr((string) ($i['desc'] ?? $i['description'] ?? ''), 0, 500),
            'recommendation' => (string) ($i['recommendation'] ?? ''),
            'decision' => (string) ($i['decision'] ?? ''),
        ], static fn ($v) => $v !== ''), $items);
        $str = ['type' => 'string'];
        $schema = [
            'type' => 'object',
            'properties' => [
                'lead' => $str,
                'sections' => ['type' => 'array', 'items' => ['type' => 'object', 'additionalProperties' => false, 'required' => ['title', 'points'], 'properties' => [
                    'title' => ['type' => 'string', 'enum' => self::SECTIONS],
                    'points' => ['type' => 'array', 'items' => $str],
                ]]],
            ],
            'required' => ['lead', 'sections'],
            'additionalProperties' => false,
        ];
        $res = $this->claude->json($model, $system, "Hónap: {$monthLabel}\nA hónap értékelt cikkei:\n" . json_encode(array_values($list), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), $schema, 'medium');
        return ['rows' => [], 'details' => trim((string) ($res['lead'] ?? '')), 'sections' => self::cleanSections($res['sections'] ?? [])];
    }

    /**
     * A csoportok a rögzített sorrendben, üres pontok és csoportok nélkül, csoportonként legfeljebb 3 ponttal.
     *
     * @param mixed $sections
     * @return list<array{title:string, points:list<string>}>
     */
    public static function cleanSections(mixed $sections): array
    {
        $by = [];
        foreach (is_array($sections) ? $sections : [] as $s) {
            $t = (string) ($s['title'] ?? '');
            if (!in_array($t, self::SECTIONS, true)) {
                continue;
            }
            foreach ((array) ($s['points'] ?? []) as $p) {
                $p = trim((string) $p);
                if ($p !== '' && count($by[$t] ?? []) < 3) {
                    $by[$t][] = $p;
                }
            }
        }
        $out = [];
        foreach (self::SECTIONS as $t) {
            if (!empty($by[$t])) {
                $out[] = ['title' => $t, 'points' => $by[$t]];
            }
        }
        return $out;
    }
}
