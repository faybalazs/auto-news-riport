<?php
declare(strict_types=1);

namespace Xperion\Press;

use Xperion\Claude\ClaudeClient;
use Xperion\Claude\ClaudeException;

/**
 * Magyar és angol cím + ajánló a forráscikkből (a bővítmény átadócsomagjának rendszerpromptja szerint),
 * strukturált JSON-kimenettel; küldés előtt ellenőrzi a mezőket és a bájthatárokat (cím ≤ 500, ajánló ≤ 10 000).
 */
final class PressWriter
{
    public const DEFAULT_MODEL = 'claude-sonnet-5-5';

    public const SYSTEM = "Az %s (%s) weboldal sajtószemléjéhez írsz cikkajánlót. %s\n"
        . "A forráscikk alapján készíts magyar és angol címet és ajánlót.\n"
        . "- Cím: a forráscikk címének hű, természetes fordítása az adott nyelven (az eredeti nyelvű cím maradhat változatlan), legfeljebb 120 karakter.\n"
        . "- Ajánló: 2–4 mondat, saját szavakkal, tárgyilagos hangnemben; mi a cikk lényege és miért érdekes ügyfélélmény- vagy retail-szempontból. Nincs szó szerinti idézet, link, emoji, felsorolás vagy formázás.\n"
        . "- Az angol változat brit angol helyesírással készül.\n"
        . '- Ne találj ki tényt, számot vagy nevet, ami nincs a forrásban.';

    public function __construct(private ClaudeClient $claude)
    {
    }

    /**
     * @param array{title:string, date:string, url:string, text:string, description?:string} $src
     * @return array{hu:array{title:string,summary:string}, en:array{title:string,summary:string}}
     */
    public function write(string $clientName, string $website, string $about, array $src, string $model = self::DEFAULT_MODEL): array
    {
        $system = sprintf(self::SYSTEM, $clientName, $website !== '' ? $website : 'saját weboldal', $about !== '' ? $about : '');
        $user = "Cím: {$src['title']}\nDátum: {$src['date']}\nURL: {$src['url']}\n\n"
            . ($src['text'] !== '' ? "A cikk szövege:\n{$src['text']}" : "A cikk szövege nem volt letölthető. Rövid leírás:\n" . ($src['description'] ?? '') . "\nCsak ebből és a címből dolgozz; ne találj ki részleteket.");
        $lang = ['type' => 'object', 'properties' => ['title' => ['type' => 'string'], 'summary' => ['type' => 'string']], 'required' => ['title', 'summary'], 'additionalProperties' => false];
        $schema = ['type' => 'object', 'properties' => ['hu' => $lang, 'en' => $lang], 'required' => ['hu', 'en'], 'additionalProperties' => false];
        $res = $this->claude->json($model, $system, $user, $schema, 'medium', 4000);
        return self::validate($res);
    }

    /**
     * @param array<string,mixed> $res
     * @return array{hu:array{title:string,summary:string}, en:array{title:string,summary:string}}
     */
    public static function validate(array $res): array
    {
        $out = [];
        foreach (['hu', 'en'] as $l) {
            $t = trim(preg_replace('/\s+/u', ' ', (string) ($res[$l]['title'] ?? '')) ?? '');
            $s = trim((string) ($res[$l]['summary'] ?? ''));
            if ($t === '' || $s === '') {
                throw new ClaudeException("A Claude válaszából hiányzik a(z) {$l} cím vagy ajánló.");
            }
            if (strlen($t) > 500 || strlen($s) > 10000) {
                throw new ClaudeException("A(z) {$l} cím vagy ajánló túl hosszú (cím ≤ 500, ajánló ≤ 10 000 bájt).");
            }
            $out[$l] = ['title' => $t, 'summary' => $s];
        }
        return $out;
    }
}
