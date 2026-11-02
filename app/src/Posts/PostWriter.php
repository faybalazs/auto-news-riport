<?php
declare(strict_types=1);

namespace Xperion\Posts;

use Xperion\Claude\ClaudeClient;
use Xperion\Claude\ClaudeException;

/**
 * LinkedIn-poszt a Posztszabályok teljes, friss tartalmából (az egyetlen szabályforrás), egy mintaposzttal,
 * a LinkedIn posztok lap mezői szerint (product.md 4.1, 6.3), plusz a kép adatai. A külső link nem kerül a szövegbe.
 */
final class PostWriter
{
    public const DEFAULT_MODEL = 'claude-opus-5-5';
    public const FIELDS = ['topic', 'opening', 'alt_opening_1', 'alt_opening_2', 'context', 'highlights', 'xperion_view', 'closing', 'hashtags', 'source_link', 'note'];

    public function __construct(private ClaudeClient $claude)
    {
    }

    /**
     * @param array<string,mixed> $source kind (cikkek|cikk|otlet), month_label, articles | idea
     * @param array<string,mixed>|null $sample mintaposzt mezői
     * @param list<string> $recentOpenings a legutóbbi posztok nyitó sorai (ne ismételje)
     * @return array<string,mixed> a FIELDS mezők + image {title, subtitle, measured, real}
     */
    public function write(string $clientName, string $rules, array $source, ?array $sample, array $recentOpenings, string $changeRequest, string $model = self::DEFAULT_MODEL): array
    {
        $system = "A(z) {$clientName} LinkedIn-oldalára írsz posztot, a cég nevében, magyarul.\n"
            . "A Posztszabályok az egyetlen mérvadó szabályforrás (hangnem, szerkezet, hossz, hashtagek, poszttípusok, kerülendők, kép). Minden pontját tartsd be:\n"
            . $rules . "\n\n"
            . "A poszt mezői (a teljes poszt ezekből áll össze, ebben a sorrendben, üres sorral elválasztva: nyitó sor, kontextus, kiemelések, szemszög, zárás, hashtagek):\n"
            . "- topic: a poszt témaköre röviden (belső használatra).\n"
            . "- opening: a nyitó sor; a LinkedIn a „…több” előtt csak ezt mutatja, ezért ez ragadja meg a figyelmet. alt_opening_1, alt_opening_2: két másik változat.\n"
            . "- context, highlights, xperion_view, closing: a poszt törzse a szabályok szerint; a highlights felsorolás is lehet (soronként egy pont).\n"
            . "- hashtags: a szabályok szerinti hashtagek egy sorban.\n"
            . "- source_link: a legfontosabb forrás linkje (első kommentbe megy, a poszt szövegébe NEM kerülhet link).\n"
            . "- note: rövid belső megjegyzés a szerkesztőnek (pl. mit érdemes ellenőrizni); ha nincs, üres.\n"
            . "- image: a poszt képe (1200×1200, a felület rajzolja): title (rövid, legfeljebb 6–8 szó), subtitle (egy rövid mondat), measured és real: opcionális „mért → valós” számpélda; ha a forrásban nincs ilyen szám, mindkettő üres. Számot ne találj ki.\n"
            . 'Csak a kapott forrásokból dolgozz; ne találj ki tényt, számot, nevet vagy idézetet.';
        $user = ['forrás' => $source];
        if ($sample) {
            $user['mintaposzt (stílus és hossz mintája, a tartalmát ne vedd át)'] = $sample;
        }
        if ($recentOpenings) {
            $user['legutóbbi nyitó sorok (ne ismételd őket)'] = $recentOpenings;
        }
        if (trim($changeRequest) !== '') {
            $user['az ügyfél módosítási kérése (ezt mindenképp vedd figyelembe)'] = $changeRequest;
        }
        $str = ['type' => 'string'];
        $schema = ['type' => 'object', 'properties' => array_fill_keys(self::FIELDS, $str) + [
            'image' => ['type' => 'object', 'properties' => ['title' => $str, 'subtitle' => $str, 'measured' => $str, 'real' => $str], 'required' => ['title', 'subtitle', 'measured', 'real'], 'additionalProperties' => false],
        ], 'required' => [...self::FIELDS, 'image'], 'additionalProperties' => false];
        $res = $this->claude->json($model, $system, json_encode($user, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT), $schema, 'high', 16000);
        $out = [];
        foreach (self::FIELDS as $f) {
            $out[$f] = trim((string) ($res[$f] ?? ''));
        }
        if ($out['opening'] === '' || $out['context'] === '' || $out['closing'] === '') {
            throw new ClaudeException('A Claude válaszából hiányzik a nyitó sor, a kontextus vagy a zárás. Próbálja újra.');
        }
        // A link nem kerülhet a poszt szövegébe (a szabályok szerint az első kommentbe megy).
        foreach (['opening', 'context', 'highlights', 'xperion_view', 'closing'] as $f) {
            $out[$f] = trim((string) preg_replace('~\s*https?://\S+~u', '', $out[$f]));
        }
        $img = (array) ($res['image'] ?? []);
        $out['image'] = ['title' => trim((string) ($img['title'] ?? '')), 'subtitle' => trim((string) ($img['subtitle'] ?? '')), 'measured' => trim((string) ($img['measured'] ?? '')), 'real' => trim((string) ($img['real'] ?? ''))];
        return $out;
    }

    /** A teljes poszt a mezőkből (a Sheet O oszlopának képlete szerint: F, I, J, L, M, N). @param array<string,mixed> $p */
    public static function fullText(array $p): string
    {
        return implode("\n\n", array_values(array_filter(array_map(static fn ($k) => trim((string) ($p[$k] ?? '')), ['opening', 'context', 'highlights', 'xperion_view', 'closing', 'hashtags']), static fn ($s) => $s !== '')));
    }
}
