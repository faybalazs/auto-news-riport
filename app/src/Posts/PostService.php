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
        $p['job'] = $this->db->one("SELECT attempts, last_error FROM post_jobs WHERE client_id = ? AND kind = 'ujra' AND ref = ?", [$cid, $code]);
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
        $a->require('ideas');
        $cid = $a->clientId() ?? throw new \InvalidArgumentException('Nincs kiválasztott ügyfél.');
        if (!in_array($kind, ['cikkek', 'cikk', 'otlet', 'ujra'], true)) {
            throw new \InvalidArgumentException('Ismeretlen generálási mód.');
        }
        $this->validateRequest($cid, $kind, $ref);
        $this->db->run('INSERT INTO post_jobs (client_id, kind, ref, requested_by, attempts, last_error, created_at) VALUES (?, ?, ?, ?, 0, \'\', ?)
            ON DUPLICATE KEY UPDATE requested_by = VALUES(requested_by), attempts = 0, last_error = \'\'', [$cid, $kind, $ref, $a->userId(), Database::now()]);
        $job = $this->db->one('SELECT * FROM post_jobs WHERE client_id = ? AND kind = ? AND ref = ?', [$cid, $kind, $ref]);
        return $this->runJob($job);
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
            'cikkek' => (int) $this->db->value("SELECT COUNT(*) FROM articles WHERE client_id = ? AND month = ? AND decision = 'linkedin' AND post_code = ''", [$cid, $ref]) > 0
                || throw new \InvalidArgumentException('Ebben a hónapban nincs poszt nélküli „LinkedIn poszt” jelölésű cikk.'),
            'cikk' => $this->db->value("SELECT 1 FROM articles WHERE client_id = ? AND id = ? AND decision = 'linkedin' AND post_code = ''", [$cid, (int) $ref])
                || throw new \InvalidArgumentException('Ebből a cikkből nem készíthető poszt (nincs „LinkedIn poszt” jelölése, vagy már van posztja).'),
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
                $set = $fields + ['status' => 'tervezet', 'status_at' => $now];
                $this->db->run('UPDATE posts SET ' . implode(', ', array_map(static fn ($k) => "$k = ?", array_keys($set))) . ' WHERE id = ?', [...array_values($set), $existing['id']]);
                $this->event($cid, $code, $userId, 'Újragenerálva', $existing['change_request'] ? 'a módosítási kérés szerint' : '');
                return;
            }
            $code = $this->nextCode($cid, $month);
            $this->db->insert('posts', $fields + ['client_id' => $cid, 'code' => $code, 'month' => $month, 'status' => 'tervezet', 'source_kind' => $kind, 'idea_id' => $ideaId, 'status_at' => $now, 'created_at' => $now]);
            if ($articleIds) {
                $this->db->run('UPDATE articles SET post_code = ?, updated_at = ? WHERE client_id = ? AND id IN (' . implode(',', array_fill(0, count($articleIds), '?')) . ')', [$code, $now, $cid, ...$articleIds]);
            }
            if ($ideaId !== null) {
                $this->db->run("UPDATE post_ideas SET status = 'elkeszult', post_code = ?, updated_at = ? WHERE id = ?", [$code, $now, $ideaId]);
            }
            $this->event($cid, $code, $userId, 'Generálva', ['cikkek' => 'a hónap LinkedIn-cikkeiből', 'cikk' => 'egy cikkből', 'otlet' => 'posztötletből'][$kind] ?? '');
        });
        $this->audit->add($cid, $userId, $existing ? 'Poszt újragenerálva' : 'Poszt generálva', $code . ' · ' . $out['topic']);
        return $code;
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
            'cikkek' => preg_match('/^LI-/', $ref) ? $this->db->all("SELECT $cols FROM articles WHERE client_id = ? AND post_code = ? ORDER BY no", [$cid, $ref])
                : $this->db->all("SELECT $cols FROM articles WHERE client_id = ? AND month = ? AND decision = 'linkedin' AND post_code = '' ORDER BY no", [$cid, $ref]),
            'cikk' => preg_match('/^LI-/', $ref) ? $this->db->all("SELECT $cols FROM articles WHERE client_id = ? AND post_code = ?", [$cid, $ref])
                : $this->db->all("SELECT $cols FROM articles WHERE client_id = ? AND id = ?", [$cid, (int) $ref]),
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
            'feladat' => $kind === 'cikkek' && count($rows) > 1 ? 'Egy havi poszt az összes cikkből, közös gondolatra építve (a szabályok szerinti visszatérő havi formátum, ha van ilyen).' : 'Egy poszt ebből a cikkből.',
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
            $set['image_data'] = json_encode(array_map(static fn ($v) => mb_substr(trim((string) $v), 0, 200), array_intersect_key($in['image'] + ['title' => '', 'subtitle' => '', 'measured' => '', 'real' => ''], array_flip(['title', 'subtitle', 'measured', 'real']))), JSON_UNESCAPED_UNICODE);
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
            $this->db->run("DELETE FROM post_jobs WHERE client_id = ? AND kind = 'ujra' AND ref = ?", [$cid, $code]);
            $this->db->run('DELETE FROM posts WHERE id = ?', [$p['id']]);
        });
        $this->audit->add($cid, $a->userId(), 'Poszt törölve', $code . ' · ' . $p['topic']);
    }

    private function notify(int $cid, string $code, string $topic, string $action, string $comment, int $actor): void
    {
        $who = match ($action) {
            'kuldes' => ['roles' => ['ugyfeladmin'], 'ops' => false, 'subject' => "Jóváhagyásra vár: {$code}", 'text' => "Új LinkedIn-poszt vár a jóváhagyására: {$code} – {$topic}.\nNézze át, és hagyja jóvá, vagy kérjen módosítást."],
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
        ];
        if ($full) {
            foreach (self::EDITABLE as $k) {
                $p[$k] = (string) ($r[$k] ?? '');
            }
            $p['image'] = (is_array($img) ? $img : []) + ['title' => '', 'subtitle' => '', 'measured' => '', 'real' => ''];
            $p['full_text'] = PostWriter::fullText($p);
            $p['idea_topic'] = (string) ($r['idea_topic'] ?? '');
        }
        return $p;
    }
}
