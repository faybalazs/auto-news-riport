<?php
declare(strict_types=1);

namespace Xperion\Posts;

use Xperion\Auth\Access;
use Xperion\Auth\AuditLog;
use Xperion\Claude\ClaudeClient;
use Xperion\Db\Database;
use Xperion\Mail\Mailer;
use Xperion\Mail\Message;
use Xperion\Task\TaskContext;
use Xperion\Web\View;

/**
 * LinkedIn-posztok (product.md 6.2/3–4, 6.3, 6.5): generálás a hónap „LinkedIn poszt” cikkeiből (egy havi poszt),
 * egy cikkből vagy egy ötletből; szerkesztés; jóváhagyási lépések Tervezet → Ügyfélnél → Jóváhagyva → Megjelent.
 * A generálás gombra azonnal fut; ha nem sikerül, a kérés megmarad, és az óránkénti futás pótolja (≤ 3 próba).
 */
final class PostService
{
    public const STATUS = ['tervezet' => 'Tervezet', 'ugyfelnel' => 'Ügyfélnél', 'jovahagyva' => 'Jóváhagyva', 'megjelent' => 'Megjelent'];
    public const MAX_ATTEMPTS = 3;
    public const EDITABLE = ['topic', 'opening', 'alt_opening_1', 'alt_opening_2', 'context', 'highlights', 'xperion_view', 'closing', 'hashtags', 'source_link', 'note'];

    public function __construct(
        private Database $db,
        private ClaudeClient $claude,
        private RuleService $rules,
        private Mailer $mailer,
        private AuditLog $audit,
        private string $baseUrl,
    ) {
    }

    /** @return list<array<string,mixed>> a legfrissebb elöl */
    public function all(Access $a): array
    {
        $cid = $a->clientId();
        if ($cid === null) {
            return [];
        }
        return array_map(fn ($r) => $this->shape($r, false), $this->db->all('SELECT p.*, i.code AS idea_code FROM posts p LEFT JOIN post_ideas i ON i.id = p.idea_id
            WHERE p.client_id = ? ORDER BY p.created_at DESC, p.code DESC', [$cid]));
    }

    /** @return array<string,mixed>|null a poszt, a teljes szöveg, a forrásai és az előzmények */
    public function get(Access $a, string $code): ?array
    {
        $cid = $a->clientId();
        $r = $cid === null ? null : $this->db->one('SELECT p.*, i.code AS idea_code, i.topic AS idea_topic FROM posts p LEFT JOIN post_ideas i ON i.id = p.idea_id WHERE p.client_id = ? AND p.code = ?', [$cid, $code]);
        if ($r === null) {
            return null;
        }
        $p = $this->shape($r, true);
        $p['articles'] = array_map(static fn ($x) => ['id' => (int) $x['id'], 'month' => (string) $x['month'], 'no' => $x['no'] === null ? null : (int) $x['no'], 'title' => (string) $x['title'], 'url' => (string) $x['url'], 'source_name' => (string) $x['source_name']],
            $this->db->all('SELECT id, month, no, title, url, source_name FROM articles WHERE client_id = ? AND post_code = ? ORDER BY month, no', [$cid, $code]));
        $p['events'] = array_map(static fn ($e) => ['action' => (string) $e['action'], 'details' => (string) $e['details'], 'user' => (string) ($e['name'] ?? 'Rendszer'), 'time_hu' => View::dateHu((string) $e['created_at'])],
            $this->db->all('SELECT e.action, e.details, e.created_at, u.name FROM post_events e LEFT JOIN users u ON u.id = e.user_id WHERE e.client_id = ? AND e.post_code = ? ORDER BY e.id DESC', [$cid, $code]));
        // a forráscikkek szerkesztéséhez: a poszt cikkei és a még poszt nélküli „LinkedIn poszt” cikkek
        $p['candidates'] = in_array($p['source_kind'], ['cikkek', 'cikk', ''], true) && $p['idea_code'] === '' ? array_map(static fn ($x) => ['id' => (int) $x['id'], 'month' => (string) $x['month'], 'title' => (string) $x['title'], 'source_name' => (string) $x['source_name'], 'in' => $x['post_code'] === $code],
            $this->db->all("SELECT id, month, title, source_name, post_code FROM articles WHERE client_id = ? AND decision = 'linkedin' AND (post_code = '' OR post_code = ?) ORDER BY month DESC, no", [$cid, $code])) : [];
        $p['job'] = $this->db->one("SELECT attempts, last_error FROM post_jobs WHERE client_id = ? AND kind = 'ujra' AND ref_hash = ?", [$cid, sha1($code)]);
        return $p;
    }

    /** A hónapok, amelyekben van még poszt nélküli „LinkedIn poszt” cikk. @return list<array{month:string, label:string, n:int}> */
    public function openMonths(Access $a): array
    {
        $cid = $a->clientId();
        if ($cid === null) {
            return [];
        }
        return array_map(static fn ($r) => ['month' => (string) $r['month'], 'label' => View::monthHu(new \DateTimeImmutable(sprintf('20%s-%s-01', substr((string) $r['month'], 0, 2), substr((string) $r['month'], 2, 2)))), 'n' => (int) $r['n']],
            $this->db->all("SELECT month, COUNT(*) AS n FROM articles WHERE client_id = ? AND decision = 'linkedin' AND post_code = '' GROUP BY month ORDER BY month DESC", [$cid]));
    }

    /** Függő (sikertelen) generálási kérések az ügyfélnél. @return list<array<string,mixed>> */
    public function pendingJobs(Access $a): array
    {
        $cid = $a->clientId();
        return $cid === null ? [] : array_map(static fn ($j) => ['kind' => (string) $j['kind'], 'ref' => (string) $j['ref'], 'attempts' => (int) $j['attempts'], 'error' => (string) $j['last_error']],
            $this->db->all('SELECT kind, ref, attempts, last_error FROM post_jobs WHERE client_id = ? ORDER BY id', [$cid]));
    }

    /**
     * Generálási kérés gombra: azonnal lefut. Siker esetén a poszt kódját adja; hibánál a kérés megmarad
     * (az óránkénti futás újrapróbálja), és kivételt dob a hiba okával.
     */
    public function request(Access $a, string $kind, string $ref): string
    {
        $cid = $a->clientId() ?? throw new \InvalidArgumentException('Nincs kiválasztott ügyfél.');
        $kind = $kind === 'cikk' ? 'cikkek' : $kind;
        match ($kind) {
            // a kijelölt cikkekből az indít posztot, aki a cikkekről dönt (ügyfél-adminisztrátor, üzemeltető)
            'cikkek' => $a->require('decide'),
            'otlet' => $a->require('ideas'),
            'ujra' => $a->can('ideas') ? null : $a->require('approve'),
            default => throw new \InvalidArgumentException('Ismeretlen generálási mód.'),
        };
        if ($kind === 'cikkek') {
            $ref = self::idList($ref);
        }
        $this->validateRequest($cid, $kind, $ref);
        return $this->runJob($this->job($cid, $kind, $ref, $a->userId()));
    }

    /** @return array<string,mixed> a (létrehozott vagy újraindított) kérés */
    private function job(int $cid, string $kind, string $ref, ?int $userId): array
    {
        $this->db->run('INSERT INTO post_jobs (client_id, kind, ref, ref_hash, requested_by, attempts, last_error, created_at) VALUES (?, ?, ?, ?, ?, 0, \'\', ?)
            ON DUPLICATE KEY UPDATE requested_by = VALUES(requested_by), attempts = 0, last_error = \'\'', [$cid, $kind, $ref, sha1($ref), $userId, Database::now()]);
        return $this->db->one('SELECT * FROM post_jobs WHERE client_id = ? AND kind = ? AND ref_hash = ?', [$cid, $kind, sha1($ref)]) ?? [];
    }

    /** „12, 7,12” → „7,12” */
    private static function idList(string $ref): string
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', preg_split('/[^0-9]+/', $ref) ?: []), static fn ($i) => $i > 0)));
        sort($ids);
        if (!$ids) {
            throw new \InvalidArgumentException('Jelöljön ki legalább egy „LinkedIn poszt” cikket.');
        }
        return implode(',', $ids);
    }

    /** Az óránkénti futás: a sikertelen generálási kérések pótlása (Aktív ügyfelek, legfeljebb 3 próba). */
    public function runPending(TaskContext $ctx): void
    {
        $jobs = $this->db->all("SELECT j.* FROM post_jobs j JOIN clients c ON c.id = j.client_id WHERE c.status = 'aktiv' AND j.attempts < ? ORDER BY j.id LIMIT 5", [self::MAX_ATTEMPTS]);
        if (!$jobs) {
            $ctx->info('Posztgenerálás: nincs függő kérés.');
            return;
        }
        foreach ($jobs as $j) {
            $label = "Posztgenerálás ({$j['kind']} {$j['ref']})";
            if ($ctx->dry) {
                $ctx->write($label, static fn () => null);
                continue;
            }
            try {
                $code = $this->runJob($j);
                $ctx->done("{$label}: elkészült – {$code}");
            } catch (\Throwable $e) {
                $ctx->error("{$label}: {$e->getMessage()}");
            }
        }
    }

    /**
     * Egy kérés végrehajtása: forrás → Claude → mentés (tranzakcióban). Hibánál a kérés próbálkozásszáma nő.
     *
     * @param array<string,mixed> $job
     */
    private function runJob(array $job): string
    {
        $cid = (int) $job['client_id'];
        try {
            $code = $this->generate($cid, (string) $job['kind'], (string) $job['ref'], $job['requested_by'] === null ? null : (int) $job['requested_by']);
            $this->db->run('DELETE FROM post_jobs WHERE id = ?', [$job['id']]);
            return $code;
        } catch (\InvalidArgumentException $e) {
            // adathiba (nincs szabály, nincs forrás): újrapróbálásnak nincs értelme
            $this->db->run('DELETE FROM post_jobs WHERE id = ?', [$job['id']]);
            throw $e;
        } catch (\Throwable $e) {
            $this->db->run('UPDATE post_jobs SET attempts = attempts + 1, last_error = ? WHERE id = ?', [mb_substr($e->getMessage(), 0, 1000), $job['id']]);
            $left = self::MAX_ATTEMPTS - ((int) $job['attempts'] + 1);
            throw new \RuntimeException($e->getMessage() . ($left > 0 ? ' A kérés megmaradt: az óránkénti futás újrapróbálja.' : ' Elérte a ' . self::MAX_ATTEMPTS . ' próbát; javítás után indítsa újra.'), 0, $e);
        }
    }

    private function validateRequest(int $cid, string $kind, string $ref): void
    {
        match ($kind) {
            'cikkek' => (int) $this->db->value("SELECT COUNT(*) FROM articles WHERE client_id = ? AND FIND_IN_SET(id, ?) AND decision = 'linkedin' AND post_code = ''", [$cid, $ref]) === count(explode(',', $ref))
                || throw new \InvalidArgumentException('A kijelölt cikkek közül valamelyikből nem készíthető poszt (nincs „LinkedIn poszt” jelölése, vagy már van posztja). Töltse újra az oldalt.'),
            'otlet' => $this->db->value("SELECT 1 FROM post_ideas WHERE client_id = ? AND code = ? AND status <> 'elvetve' AND (post_code = '' OR post_code NOT IN (SELECT code FROM posts WHERE client_id = ?))", [$cid, $ref, $cid])
                || throw new \InvalidArgumentException('Ebből az ötletből nem készíthető poszt (elvetett, vagy már van posztja).'),
            'ujra' => in_array($this->db->value('SELECT status FROM posts WHERE client_id = ? AND code = ?', [$cid, $ref]), ['tervezet', 'ugyfelnel'], true)
                || throw new \InvalidArgumentException('Csak tervezet vagy ügyfélnél lévő poszt generálható újra.'),
        };
    }

    /** A generálás magja; az új vagy frissített poszt kódját adja. */
    private function generate(int $cid, string $kind, string $ref, ?int $userId): string
    {
        $this->validateRequest($cid, $kind, $ref);
        $client = (string) $this->db->value('SELECT name FROM clients WHERE id = ?', [$cid]);
        $rules = $this->rules->asText($cid);
        if ($rules === '') {
            throw new \InvalidArgumentException('Nincsenek Posztszabályok ennél az ügyfélnél. Előbb vegye fel őket (Posztszabályok oldal).');
        }
        $existing = $kind === 'ujra' ? $this->db->one('SELECT * FROM posts WHERE client_id = ? AND code = ?', [$cid, $ref]) : null;
        $srcKind = $kind;
        if ($existing !== null) {
            // A Sheetből átvett posztnak nincs rögzített forrásfajtája: ha vannak hozzá kötött cikkek, azokból készül újra.
            $srcKind = (string) $existing['source_kind'] !== '' ? (string) $existing['source_kind']
                : ((int) $this->db->value('SELECT COUNT(*) FROM articles WHERE client_id = ? AND post_code = ?', [$cid, $existing['code']]) > 0 ? 'cikkek'
                : ($existing['idea_id'] !== null ? 'otlet' : throw new \InvalidArgumentException('Ennek a posztnak nincs ismert forrása (cikk vagy ötlet); szerkessze kézzel.')));
        }
        [$source, $articleIds, $ideaId, $month] = $this->source($cid, $srcKind, $existing ? $this->sourceRef($cid, array_merge($existing, ['source_kind' => $srcKind])) : $ref);
        $sample = $this->sample($cid, $existing ? (string) $existing['code'] : '');
        $recent = array_map(static fn ($r) => (string) $r['opening'], $this->db->all("SELECT opening FROM posts WHERE client_id = ? AND opening <> '' AND code <> ? ORDER BY created_at DESC LIMIT 6", [$cid, $ref]));
        $model = (string) ($this->db->value("SELECT value FROM client_settings WHERE client_id = ? AND name = 'modell_linkedin'", [$cid]) ?: PostWriter::DEFAULT_MODEL);
        $out = (new PostWriter($this->claude))->write($client, $rules, $source, $sample, $recent, (string) ($existing['change_request'] ?? ''), $model);

        $now = Database::now();
        $fields = array_intersect_key($out, array_flip(self::EDITABLE));
        $fields['image_data'] = json_encode($out['image'], JSON_UNESCAPED_UNICODE);
        $fields['source_articles'] = $source['kind'] === 'otlet' ? 'Ötlet: ' . ($source['idea']['code'] ?? '') : implode('; ', array_map(static fn ($x) => $x['source'] . ': ' . $x['title'], $source['articles'] ?? []));
        $fields['model'] = $model;
        $fields['updated_at'] = $now;
        $this->db->transaction(function () use ($existing, $cid, $kind, $fields, $articleIds, $ideaId, $month, $now, $userId, &$code): void {
            if ($existing !== null) {
                $code = (string) $existing['code'];
                $set = $fields + ['status' => 'ugyfelnel', 'status_at' => $now, 'change_request' => null];
                $this->db->run('UPDATE posts SET ' . implode(', ', array_map(static fn ($k) => "$k = ?", array_keys($set))) . ' WHERE id = ?', [...array_values($set), $existing['id']]);
                $this->event($cid, $code, $userId, 'Újragenerálva, jóváhagyásra elküldve', $existing['change_request'] ? 'a módosítási kérés szerint: ' . $existing['change_request'] : '');
                return;
            }
            $code = $this->nextCode($cid, $month);
            $this->db->insert('posts', $fields + ['client_id' => $cid, 'code' => $code, 'month' => $month, 'status' => 'ugyfelnel', 'source_kind' => $kind === 'cikk' ? 'cikkek' : $kind, 'idea_id' => $ideaId, 'status_at' => $now, 'created_at' => $now]);
            if ($articleIds) {
                $this->db->run('UPDATE articles SET post_code = ?, updated_at = ? WHERE client_id = ? AND id IN (' . implode(',', array_fill(0, count($articleIds), '?')) . ')', [$code, $now, $cid, ...$articleIds]);
            }
            if ($ideaId !== null) {
                $this->db->run("UPDATE post_ideas SET status = 'elkeszult', post_code = ?, updated_at = ? WHERE id = ?", [$code, $now, $ideaId]);
            }
            $this->event($cid, $code, $userId, 'Generálva, jóváhagyásra elküldve', $kind === 'otlet' ? 'posztötletből' : (count($articleIds) > 1 ? count($articleIds) . ' kijelölt cikkből' : 'egy cikkből'));
        });
        $this->audit->add($cid, $userId, $existing ? 'Poszt újragenerálva' : 'Poszt generálva', $code . ' · ' . $out['topic']);
        // Egyedi kép: ha nem sikerül, a poszt a sablonképpel marad, és az „Új kép” paranccsal később kérhető.
        try {
            $this->designImage($cid, $code, $existing && $existing['change_request'] ? (string) $existing['change_request'] : '', $model);
        } catch (\Throwable $e) {
            $this->event($cid, $code, null, 'A kép nem készült el', $e->getMessage());
        }
        $this->notify($cid, $code, (string) $out['topic'], 'kuldes', '', $userId ?? 0);
        return $code;
    }

    /**
     * A poszt forráscikkeinek módosítása (a kijelölt „LinkedIn poszt” cikkek); utána kérésre újraírja a posztot.
     *
     * @param list<int> $ids
     * @return array<string,mixed>
     */
    public function setArticles(Access $a, string $code, array $ids, bool $regenerate): array
    {
        $a->require('decide');
        $cid = $a->clientId() ?? throw new \InvalidArgumentException('Nincs kiválasztott ügyfél.');
        $p = $this->db->one('SELECT id, status, idea_id FROM posts WHERE client_id = ? AND code = ?', [$cid, $code]) ?? throw new \InvalidArgumentException('Nincs ilyen poszt.');
        if (!in_array($p['status'], ['tervezet', 'ugyfelnel'], true)) {
            throw new \InvalidArgumentException('Jóváhagyott vagy megjelent poszt forrásai nem módosíthatók.');
        }
        if ($p['idea_id'] !== null) {
            throw new \InvalidArgumentException('Ötletből készült poszt forrása az ötlet; cikkeket nem lehet hozzá rendelni.');
        }
        $ids = explode(',', self::idList(implode(',', array_map('intval', $ids))));
        $ok = (int) $this->db->value("SELECT COUNT(*) FROM articles WHERE client_id = ? AND FIND_IN_SET(id, ?) AND decision = 'linkedin' AND (post_code = '' OR post_code = ?)", [$cid, implode(',', $ids), $code]);
        if ($ok !== count($ids)) {
            throw new \InvalidArgumentException('A kijelölt cikkek közül valamelyik nem „LinkedIn poszt” jelölésű, vagy már másik poszthoz tartozik. Töltse újra az oldalt.');
        }
        $before = array_map(static fn ($r) => (int) $r['id'], $this->db->all('SELECT id FROM articles WHERE client_id = ? AND post_code = ?', [$cid, $code]));
        $now = Database::now();
        $this->db->transaction(function () use ($cid, $code, $ids, $now, $p): void {
            $this->db->run("UPDATE articles SET post_code = '', updated_at = ? WHERE client_id = ? AND post_code = ? AND NOT FIND_IN_SET(id, ?)", [$now, $cid, $code, implode(',', $ids)]);
            $this->db->run('UPDATE articles SET post_code = ?, updated_at = ? WHERE client_id = ? AND FIND_IN_SET(id, ?)', [$code, $now, $cid, implode(',', $ids)]);
            $this->db->run("UPDATE posts SET source_kind = 'cikkek', updated_at = ? WHERE id = ?", [$now, $p['id']]);
        });
        $added = count(array_diff(array_map('intval', $ids), $before));
        $removed = count(array_diff($before, array_map('intval', $ids)));
        $this->event($cid, $code, $a->userId(), 'Forráscikkek módosítva', "+{$added} / -{$removed} cikk, összesen " . count($ids));
        $this->audit->add($cid, $a->userId(), 'Poszt: forráscikkek módosítva', $code . " (+{$added} / -{$removed})");
        if ($regenerate) {
            $this->runJob($this->job($cid, 'ujra', $code, $a->userId()));
        }
        return $this->get($a, $code) ?? [];
    }

    /** Új egyedi kép kérése (megjegyzéssel is). */
    public function newImage(Access $a, string $code, string $request): array
    {
        if (!$a->can('ideas')) {
            $a->require('approve');
        }
        $cid = $a->clientId() ?? throw new \InvalidArgumentException('Nincs kiválasztott ügyfél.');
        $st = $this->db->value('SELECT status FROM posts WHERE client_id = ? AND code = ?', [$cid, $code]) ?? throw new \InvalidArgumentException('Nincs ilyen poszt.');
        if ($st === 'megjelent') {
            throw new \InvalidArgumentException('A megjelent poszt képe nem cserélhető.');
        }
        $this->designImage($cid, $code, trim($request), (string) ($this->db->value("SELECT value FROM client_settings WHERE client_id = ? AND name = 'modell_linkedin'", [$cid]) ?: PostWriter::DEFAULT_MODEL), $a->userId());
        return $this->get($a, $code) ?? [];
    }

    private function designImage(int $cid, string $code, string $request, string $model, ?int $userId = null): void
    {
        $p = $this->db->one('SELECT topic, opening, highlights, image_data FROM posts WHERE client_id = ? AND code = ?', [$cid, $code]) ?? throw new \InvalidArgumentException('Nincs ilyen poszt.');
        $c = $this->db->one('SELECT name, website FROM clients WHERE id = ?', [$cid]);
        $rules = implode("\n", array_map(static fn ($r) => '- ' . $r['name'] . ': ' . $r['value'], $this->db->all("SELECT name, value FROM post_rules WHERE client_id = ? AND (name LIKE '%kép%' OR name LIKE '%szín%' OR name LIKE '%vizuál%' OR name LIKE '%arculat%' OR name LIKE '%formátum%') ORDER BY sort", [$cid])));
        $img = json_decode((string) $p['image_data'], true) ?: [];
        $post = ['topic' => $p['topic'], 'opening' => $p['opening'], 'highlights' => $p['highlights'], 'image' => array_diff_key($img, ['spec' => 1]),
            'articles' => array_map(static fn ($r) => ['source' => $r['source_name'], 'title' => $r['title'], 'description' => $r['description']], $this->db->all('SELECT source_name, title, description FROM articles WHERE client_id = ? AND post_code = ? ORDER BY no', [$cid, $code]))];
        $recent = array_values(array_filter(array_map(static fn ($r) => (string) ((json_decode((string) $r['image_data'], true) ?: [])['spec']['layout'] ?? ''), $this->db->all('SELECT image_data FROM posts WHERE client_id = ? AND code <> ? ORDER BY updated_at DESC LIMIT 4', [$cid, $code]))));
        $site = preg_replace('~^https?://|/$~', '', (string) ($c['website'] ?? '')) ?: 'xperion.hu';
        $res = (new ImageDesigner($this->claude))->design((string) $c['name'], $site, $rules, $post, $request, $model, $recent);
        $img['spec'] = $res['spec'];
        $this->db->run('UPDATE posts SET image_svg = ?, image_data = ?, updated_at = ? WHERE client_id = ? AND code = ?', [$res['svg'], json_encode($img, JSON_UNESCAPED_UNICODE), Database::now(), $cid, $code]);
        $this->event($cid, $code, $userId, 'Egyedi kép elkészült', $request);
    }

    /** @param array<string,mixed> $post */
    private function sourceRef(int $cid, array $post): string
    {
        return match ((string) $post['source_kind']) {
            'otlet' => (string) $this->db->value('SELECT code FROM post_ideas WHERE id = ?', [$post['idea_id']]),
            default => (string) $post['code'],
        };
    }

    /**
     * A forrás a Claude-nak, plusz a hozzá tartozó cikkek / ötlet és a poszt hónapja.
     *
     * @return array{0:array<string,mixed>, 1:list<int>, 2:?int, 3:string}
     */
    private function source(int $cid, string $kind, string $ref): array
    {
        $cols = 'id, month, source_name, published_on, title, description, reason, url';
        $rows = match ($kind) {
            'cikkek', 'cikk' => preg_match('/^LI-/', $ref) ? $this->db->all("SELECT $cols FROM articles WHERE client_id = ? AND post_code = ? ORDER BY month, no", [$cid, $ref])
                : $this->db->all("SELECT $cols FROM articles WHERE client_id = ? AND FIND_IN_SET(id, ?) ORDER BY month, no", [$cid, $ref]),
            default => [],
        };
        if ($kind === 'otlet') {
            $idea = $this->db->one('SELECT * FROM post_ideas WHERE client_id = ? AND code = ?', [$cid, $ref]);
            if ($idea === null) {
                throw new \InvalidArgumentException('A poszt forrása (ötlet) már nem létezik; szerkessze kézzel.');
            }
            $src = ['kind' => 'otlet', 'idea' => ['code' => $idea['code'], 'téma' => $idea['topic'], 'fő üzenet' => $idea['message'], 'poszttípus' => $idea['type'], 'forrás- vagy fotólink' => $idea['link'], 'beküldő' => $idea['submitter'], 'megjegyzés' => $idea['note']]];
            return [$src, [], (int) $idea['id'], (new \DateTimeImmutable('now', new \DateTimeZone('Europe/Budapest')))->format('ym')];
        }
        if (!$rows) {
            throw new \InvalidArgumentException('A poszt forráscikkei nem találhatók.');
        }
        $month = (string) $rows[0]['month'];
        $label = View::monthHu(new \DateTimeImmutable(sprintf('20%s-%s-01', substr($month, 0, 2), substr($month, 2, 2))));
        $src = ['kind' => $kind, 'hónap' => $label,
            'feladat' => count($rows) > 1 ? 'Egy közös poszt a kijelölt cikkekből, közös gondolatra építve (ha a szabályokban van visszatérő havi formátum, és a cikkek ahhoz illenek, azt használd).' : 'Egy poszt ebből a cikkből.',
            'articles' => array_map(static fn ($r) => ['source' => $r['source_name'], 'date' => $r['published_on'], 'title' => $r['title'], 'description' => $r['description'], 'why_relevant' => $r['reason'], 'url' => $r['url']], $rows)];
        return [$src, array_map(static fn ($r) => (int) $r['id'], $rows), null, $month];
    }

    /** Mintaposzt: az LI-2609-01, vagy a legutóbbi jóváhagyott/megjelent poszt. @return array<string,string>|null */
    private function sample(int $cid, string $except): ?array
    {
        $r = $this->db->one("SELECT * FROM posts WHERE client_id = ? AND code = 'LI-2609-01' AND code <> ?", [$cid, $except])
            ?? $this->db->one("SELECT * FROM posts WHERE client_id = ? AND status IN ('jovahagyva','megjelent') AND code <> ? ORDER BY status_at DESC, created_at DESC LIMIT 1", [$cid, $except]);
        if ($r === null) {
            return null;
        }
        return array_filter(array_map(static fn ($k) => (string) ($r[$k] ?? ''), array_combine(['opening', 'context', 'highlights', 'xperion_view', 'closing', 'hashtags'], ['opening', 'context', 'highlights', 'xperion_view', 'closing', 'hashtags'])), static fn ($v) => trim($v) !== '');
    }

    /**
     * Mezők szerkesztése. Tervezet és ügyfélnél lévő posztot az ötletkezelő szerepkörök szerkeszthetnek,
     * jóváhagyottat csak az üzemeltető; megjelentet senki.
     *
     * @param array<string,mixed> $in
     * @return array<string,mixed>
     */
    public function update(Access $a, string $code, array $in): array
    {
        $a->require('ideas');
        $cid = $a->clientId() ?? throw new \InvalidArgumentException('Nincs kiválasztott ügyfél.');
        $p = $this->db->one('SELECT id, status FROM posts WHERE client_id = ? AND code = ?', [$cid, $code]) ?? throw new \InvalidArgumentException('Nincs ilyen poszt.');
        if ($p['status'] === 'megjelent' || ($p['status'] === 'jovahagyva' && !$a->can('ops'))) {
            throw new \InvalidArgumentException($p['status'] === 'megjelent' ? 'A megjelent poszt nem szerkeszthető.' : 'A jóváhagyott posztot csak az üzemeltető módosíthatja.');
        }
        $set = [];
        foreach (self::EDITABLE as $k) {
            if (array_key_exists($k, $in)) {
                $set[$k] = $k === 'topic' || $k === 'hashtags' || str_starts_with($k, 'alt_opening') || $k === 'opening' ? mb_substr(trim((string) $in[$k]), 0, $k === 'topic' ? 255 : 500) : trim((string) $in[$k]);
            }
        }
        if (isset($set['source_link']) && $set['source_link'] !== '' && !preg_match('~^https?://~i', $set['source_link'])) {
            throw new \InvalidArgumentException('A forráslink https://-sel kezdődjön.');
        }
        if (isset($in['image']) && is_array($in['image'])) {
            $prev = json_decode((string) $this->db->value('SELECT image_data FROM posts WHERE id = ?', [$p['id']]), true) ?: [];
            $img = array_map(static fn ($v) => mb_substr(trim(str_replace("\r\n", "\n", (string) $v)), 0, 200), array_intersect_key($in['image'] + ['title' => '', 'subtitle' => '', 'measured' => '', 'real' => ''], array_flip(['title', 'subtitle', 'measured', 'real'])));
            if (isset($prev['spec']) && is_array($prev['spec'])) {
                // a kép szövegének javítása: a sablonból azonnal újrarajzolva, Claude nélkül
                $spec = $prev['spec'];
                $spec['title'] = $img['title'] !== '' ? $img['title'] : ($spec['title'] ?? '');
                $spec['subtitle'] = $img['subtitle'];
                $spec['example'] = (array) ($spec['example'] ?? []);
                $spec['example']['measured_value'] = $img['measured'];
                $spec['example']['real_value'] = $img['real'];
                $img['spec'] = $spec;
                $site = preg_replace('~^https?://|/$~', '', (string) $this->db->value('SELECT website FROM clients WHERE id = ?', [$cid])) ?: 'xperion.hu';
                $set['image_svg'] = SvgSanitizer::clean(ImageTemplates::render($spec, $site));
            }
            $set['image_data'] = json_encode($img, JSON_UNESCAPED_UNICODE);
        }
        if (($set['opening'] ?? 'x') === '') {
            throw new \InvalidArgumentException('A nyitó sor nem lehet üres.');
        }
        if ($set) {
            $set['updated_at'] = Database::now();
            $this->db->run('UPDATE posts SET ' . implode(', ', array_map(static fn ($k) => "$k = ?", array_keys($set))) . ' WHERE id = ?', [...array_values($set), $p['id']]);
            $this->event($cid, $code, $a->userId(), 'Szerkesztve', implode(', ', array_keys(array_diff_key($set, ['updated_at' => 1]))));
        }
        return $this->get($a, $code) ?? [];
    }

    /**
     * Állapotváltás: kuldes (üzemeltető: Tervezet → Ügyfélnél), jovahagyas és modositas (jóváhagyó: Ügyfélnél → Jóváhagyva /
     * Tervezet megjegyzéssel), megjelent (közzétevő: Jóváhagyva → Megjelent). Értesítés a következő szereplőnek.
     *
     * @return array<string,mixed>
     */
    public function transition(Access $a, string $code, string $action, string $comment = ''): array
    {
        $cid = $a->clientId() ?? throw new \InvalidArgumentException('Nincs kiválasztott ügyfél.');
        $p = $this->db->one('SELECT id, status, topic, idea_id FROM posts WHERE client_id = ? AND code = ?', [$cid, $code]) ?? throw new \InvalidArgumentException('Nincs ilyen poszt.');
        [$perm, $from, $to, $label] = match ($action) {
            'kuldes' => ['ops', ['tervezet'], 'ugyfelnel', 'Elküldve jóváhagyásra'],
            'jovahagyas' => ['approve', ['ugyfelnel'], 'jovahagyva', 'Jóváhagyva'],
            'modositas' => ['approve', ['ugyfelnel'], 'tervezet', 'Módosítást kért'],
            'megjelent' => ['publish', ['jovahagyva'], 'megjelent', 'Megjelent a LinkedInen'],
            'vissza' => ['ops', ['ugyfelnel', 'jovahagyva', 'megjelent'], 'tervezet', 'Visszaállítva tervezetre'],
            default => throw new \InvalidArgumentException('Ismeretlen művelet.'),
        };
        $a->require($perm);
        if (!in_array($p['status'], $from, true)) {
            throw new \InvalidArgumentException('Ez a lépés most nem lehetséges (a poszt állapota: ' . self::STATUS[$p['status']] . '). Töltse újra az oldalt.');
        }
        $comment = trim($comment);
        if ($action === 'modositas' && $comment === '') {
            throw new \InvalidArgumentException('Írja le röviden, mit kell módosítani.');
        }
        $now = Database::now();
        $this->db->run('UPDATE posts SET status = ?, status_at = ?, change_request = ?, updated_at = ? WHERE id = ?',
            [$to, $now, $action === 'modositas' ? $comment : ($action === 'jovahagyas' || $action === 'kuldes' ? null : $this->db->value('SELECT change_request FROM posts WHERE id = ?', [$p['id']])), $now, $p['id']]);
        if ($p['idea_id'] !== null && in_array($to, ['jovahagyva', 'megjelent'], true)) {
            $this->db->run('UPDATE post_ideas SET status = ?, updated_at = ? WHERE id = ?', [$to === 'jovahagyva' ? 'jovahagyva' : 'utemezve', $now, $p['idea_id']]);
        }
        $this->event($cid, $code, $a->userId(), $label, $comment);
        $this->audit->add($cid, $a->userId(), 'Poszt: ' . mb_strtolower($label), $code . ($comment !== '' ? ' · ' . $comment : ''));
        if ($action === 'modositas') {
            // A Claude a kérés szerint azonnal újraírja, és visszakerül jóváhagyásra; ha nem sikerül, az óránkénti futás pótolja.
            try {
                $this->runJob($this->job($cid, 'ujra', $code, $a->userId()));
            } catch (\Throwable $e) {
                $this->notify($cid, $code, (string) $p['topic'], 'modositas', $comment . "\n\nAz automatikus újraírás nem sikerült: " . $e->getMessage(), $a->userId());
            }
            return $this->get($a, $code) ?? [];
        }
        $this->notify($cid, $code, (string) $p['topic'], $action, $comment, $a->userId());
        return $this->get($a, $code) ?? [];
    }

    /** Poszt törlése: a szöveg, a kép és az előzmények törlődnek; a forráscikkek és az ötlet újra küldhetők generálásra. */
    public function delete(Access $a, string $code): void
    {
        $a->require('deletePost');
        $cid = $a->clientId() ?? throw new \InvalidArgumentException('Nincs kiválasztott ügyfél.');
        $p = $this->db->one('SELECT id, topic, idea_id FROM posts WHERE client_id = ? AND code = ?', [$cid, $code]) ?? throw new \InvalidArgumentException('Nincs ilyen poszt.');
        $this->db->transaction(function () use ($p, $cid, $code): void {
            $this->db->run("UPDATE articles SET post_code = '' WHERE client_id = ? AND post_code = ?", [$cid, $code]);
            if ($p['idea_id'] !== null) {
                $this->db->run("UPDATE post_ideas SET status = 'uj', post_code = '' WHERE id = ?", [$p['idea_id']]);
            }
            $this->db->run('DELETE FROM post_events WHERE client_id = ? AND post_code = ?', [$cid, $code]);
            $this->db->run("DELETE FROM post_jobs WHERE client_id = ? AND kind = 'ujra' AND ref_hash = ?", [$cid, sha1($code)]);
            $this->db->run('DELETE FROM posts WHERE id = ?', [$p['id']]);
        });
        $this->audit->add($cid, $a->userId(), 'Poszt törölve', $code . ' · ' . $p['topic']);
    }

    private function notify(int $cid, string $code, string $topic, string $action, string $comment, int $actor): void
    {
        $who = match ($action) {
            'kuldes' => ['roles' => ['ugyfeladmin'], 'ops' => true, 'subject' => "Jóváhagyásra vár: {$code}", 'text' => "Új LinkedIn-poszt vár a jóváhagyására: {$code} – {$topic}.\nNézze át, és hagyja jóvá, vagy kérjen módosítást."],
            'jovahagyas' => ['roles' => ['tartalom'], 'ops' => true, 'subject' => "Közzétehető: {$code}", 'text' => "A(z) {$code} posztot jóváhagyták ({$topic}).\nMásolja ki a szöveget és a képet, időzítse a LinkedInen, majd jelölje megjelentnek."],
            'modositas' => ['roles' => [], 'ops' => true, 'subject' => "Módosítási kérés: {$code}", 'text' => "Módosítást kértek a(z) {$code} posztra ({$topic}):\n\n{$comment}"],
            default => null,
        };
        if ($who === null || !$this->mailer->configured()) {
            return;
        }
        $to = [];
        if ($who['roles']) {
            $to = array_column($this->db->all("SELECT u.email FROM users u JOIN memberships m ON m.user_id = u.id WHERE m.client_id = ? AND u.status = 'aktiv' AND m.role IN ('" . implode("','", $who['roles']) . "') AND u.id <> ?", [$cid, $actor]), 'email');
        }
        if ($who['ops']) {
            $to = [...$to, ...array_column($this->db->all("SELECT email FROM users WHERE is_operator = 1 AND status = 'aktiv' AND id <> ?", [$actor]), 'email')];
        }
        $to = array_values(array_unique(array_map('strval', $to)));
        if (!$to) {
            return;
        }
        $link = $this->baseUrl !== '' ? "\n\n" . $this->baseUrl . 'index.php?p=poszt&kod=' . rawurlencode($code) : '';
        try {
            $this->mailer->send(new Message($to, $who['subject'], $who['text'] . $link));
        } catch (\Throwable $e) {
            $this->audit->add($cid, null, 'Poszt-értesítés sikertelen', $code . ' · ' . $e->getMessage());
        }
    }

    private function event(int $cid, string $code, ?int $userId, string $action, string $details = ''): void
    {
        $this->db->insert('post_events', ['client_id' => $cid, 'post_code' => $code, 'user_id' => $userId, 'action' => mb_substr($action, 0, 100), 'details' => mb_substr($details, 0, 1000), 'created_at' => Database::now()]);
    }

    private function nextCode(int $cid, string $month): string
    {
        $prefix = 'LI-' . $month . '-';
        $n = 0;
        foreach ($this->db->all('SELECT code FROM posts WHERE client_id = ? AND code LIKE ?', [$cid, $prefix . '%']) as $r) {
            $n = max($n, (int) substr((string) $r['code'], strlen($prefix)));
        }
        return $prefix . str_pad((string) ($n + 1), 2, '0', STR_PAD_LEFT);
    }

    /** @param array<string,mixed> $r @return array<string,mixed> */
    private function shape(array $r, bool $full): array
    {
        $img = json_decode((string) ($r['image_data'] ?? ''), true);
        $p = [
            'code' => (string) $r['code'], 'month' => (string) $r['month'], 'status' => (string) $r['status'], 'status_label' => self::STATUS[$r['status']] ?? (string) $r['status'],
            'topic' => (string) $r['topic'], 'source_kind' => (string) $r['source_kind'], 'source_articles' => (string) ($r['source_articles'] ?? ''), 'idea_code' => (string) ($r['idea_code'] ?? ''),
            'opening' => (string) $r['opening'], 'created_at' => (string) $r['created_at'], 'created_hu' => View::dateHu((string) $r['created_at']), 'status_hu' => $r['status_at'] ? View::dateHu((string) $r['status_at']) : '',
            'change_request' => (string) ($r['change_request'] ?? ''), 'model' => (string) ($r['model'] ?? ''),
            'thumb' => $full ? '' : (string) ($r['image_svg'] ?? ''),
        ];
        if ($full) {
            foreach (self::EDITABLE as $k) {
                $p[$k] = (string) ($r[$k] ?? '');
            }
            $p['image'] = (is_array($img) ? array_diff_key($img, ['spec' => 1]) : []) + ['title' => '', 'subtitle' => '', 'measured' => '', 'real' => ''];
            if (is_array($img['spec'] ?? null)) {
                // a képen ténylegesen szereplő szövegek (a szerkesztő ezeket mutatja)
                $sp = $img['spec'];
                $p['image'] = ['title' => (string) ($sp['title'] ?? ''), 'subtitle' => (string) ($sp['subtitle'] ?? ''), 'measured' => (string) ($sp['example']['measured_value'] ?? ''), 'real' => (string) ($sp['example']['real_value'] ?? '')];
                $p['layout'] = (string) ($sp['layout'] ?? '');
            }
            $p['full_text'] = PostWriter::fullText($p);
            $p['image_svg'] = SvgFonts::embed((string) ($r['image_svg'] ?? ''));
            $p['idea_topic'] = (string) ($r['idea_topic'] ?? '');
        }
        return $p;
    }
}
