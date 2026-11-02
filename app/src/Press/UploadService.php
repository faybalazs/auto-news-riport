<?php
declare(strict_types=1);

namespace Xperion\Press;

use Xperion\Auth\Access;
use Xperion\Auth\AuditLog;
use Xperion\Claude\ClaudeClient;
use Xperion\Db\Database;
use Xperion\Lock;
use Xperion\Log\LocalLog;
use Xperion\Log\LogEntry;
use Xperion\Mail\Mailer;
use Xperion\Mail\Message;
use Xperion\Task\RunResult;
use Xperion\Task\TaskContext;

/**
 * Élő feltöltés (product.md 6.2/2, integration-task.md): a „Mehet” cikkek magyar–angol ajánlópárként
 * az ügyfél WordPress-oldalára kerülnek a bővítmény deploy_pair végpontján. Sorban, párhuzamosság nélkül.
 * Szárazon: fordítás és get_pair fut, írás (WordPress, adatbázis) nincs.
 */
final class UploadService
{
    public const MAX_ATTEMPTS = 3;
    public const PER_RUN = 10;
    public const STATES = ['' => 'Feltöltésre vár', 'kint' => 'Kint', 'duplikatum' => 'Már fent volt', 'hiba' => 'Hiba', 'zarolt' => 'Folyamatban', 'reszben' => 'Részben kint'];

    /** @var callable(array<string,mixed>):WpBridgeClient */
    private $wpFor;
    /** @var callable(int):void */
    private $sleep;

    /**
     * @param callable(array<string,mixed>):WpBridgeClient $wpFor az ügyfél (clients sor) WordPress-kliense; hiányzó beállításnál kivételt dob
     */
    public function __construct(
        private Database $db,
        private ClaudeClient $claude,
        callable $wpFor,
        private SourceText $source,
        private Mailer $mailer,
        private AuditLog $audit,
        private LocalLog $log,
        private string $lockFile,
        ?callable $sleep = null,
    ) {
        $this->wpFor = $wpFor;
        $this->sleep = $sleep ?? static function (int $ms): void { usleep($ms * 1000); };
    }

    /** Kapcsolatpróba az aktuális ügyfél WordPress-ével (csak olvas). @return array<string,mixed> */
    public function check(Access $a): array
    {
        $a->require('ops');
        $client = $a->client() ?? throw new \InvalidArgumentException('Nincs kiválasztott ügyfél.');
        $wp = ($this->wpFor)($client);
        $c = $wp->context();
        $cats = [];
        foreach ($c['categories'] ?? [] as $cat) {
            $cats[(int) $cat['id']] = $cat['name'] . (($cat['language'] ?? '') !== '' ? ' (' . $cat['language'] . ')' : '');
        }
        $def = $c['defaults'] ?? [];
        return [
            'site' => $wp->baseUrl(),
            'version' => (string) ($c['version'] ?? '?'),
            'language_engine' => (string) ($c['language_engine'] ?? ''),
            'can_publish' => (bool) ($c['can_publish'] ?? false),
            'can_upload' => (bool) ($c['can_upload'] ?? false),
            'hu_category' => isset($def['hu_category']) && $def['hu_category'] ? ($cats[(int) $def['hu_category']] ?? '#' . $def['hu_category']) : '',
            'en_category' => isset($def['en_category']) && $def['en_category'] ? ($cats[(int) $def['en_category']] ?? '#' . $def['en_category']) : '',
            'pairs' => count($c['pairs'] ?? []),
        ];
    }

    /** Kézi futtatás a Vezérlőpultról, az aktuális ügyfélre. */
    public function runForAccess(Access $a, bool $dry): RunResult
    {
        $a->require('ops');
        $client = $a->client();
        $label = 'Feltöltés: ' . ($client['name'] ?? '–');
        $start = hrtime(true);
        $now = new \DateTimeImmutable('now');
        $ctx = new TaskContext($dry, $now, static fn () => throw new \LogicException('nincs Sheets'));
        $data = [];
        if ($client === null) {
            $ctx->error('Nincs kiválasztott ügyfél.');
        } elseif (($lock = Lock::acquire($this->lockFile)) === null) {
            $ctx->error('Egy másik futás folyamatban van. Próbálja újra pár perc múlva.');
        } else {
            try {
                $data = $this->runForClient((int) $client['id'], $ctx);
            } catch (\Throwable $e) {
                $ctx->error($e->getMessage());
            } finally {
                $lock->release();
            }
        }
        $result = new RunResult('feltoltes', $label, $dry, !$ctx->hasErrors(), $ctx->lines(), (int) ((hrtime(true) - $start) / 1_000_000), $data);
        $this->log->append(new LogEntry($now, 'feltoltes', $label, $dry, 'admin', $result->ok, $result->summary(), $result->durationMs));
        if (!$dry && $client !== null && ($data['items'] ?? []) !== []) {
            $this->audit->add((int) $client['id'], $a->userId(), $result->ok ? 'Feltöltés' : 'Feltöltés hibával', $result->summary());
        }
        return $result;
    }

    /**
     * Az óránkénti futás: minden Aktív ügyfél. Belépési hibánál vagy a külső-link bővítmény hibájánál
     * az egész futás megáll, és az üzemeltető e-mailt kap.
     */
    public function runAll(TaskContext $ctx): void
    {
        $clients = $this->db->all("SELECT id, name FROM clients WHERE status = 'aktiv' ORDER BY name");
        foreach ($clients as $c) {
            $n = (int) $this->db->value("SELECT COUNT(*) FROM articles WHERE client_id = ? AND decision = 'mehet' AND uploaded = 0", [$c['id']]);
            if ($n === 0) {
                continue;
            }
            $ctx->info('— ' . $c['name'] . ' —');
            try {
                $r = $this->runForClient((int) $c['id'], $ctx);
                if (!empty($r['stopped'])) {
                    break;
                }
            } catch (\Throwable $e) {
                $ctx->error($c['name'] . ': ' . $e->getMessage());
            }
        }
    }

    /**
     * Egy ügyfél „Mehet” cikkei (legfeljebb PER_RUN darab futásonként).
     *
     * @return array{items:list<array<string,mixed>>, stopped:bool}
     */
    public function runForClient(int $clientId, TaskContext $ctx): array
    {
        $client = $this->db->one('SELECT id, name, website, status FROM clients WHERE id = ?', [$clientId]) ?? throw new \InvalidArgumentException('Nincs ilyen ügyfél.');
        $rows = $this->db->all("SELECT id, month, no, source_name, published_on, title, description, url, press_key, wp_state, hu_title, hu_summary, en_title, en_summary, upload_attempts
            FROM articles WHERE client_id = ? AND decision = 'mehet' AND uploaded = 0 AND wp_state <> 'duplikatum' AND upload_attempts < ?
            ORDER BY month, COALESCE(no, 9999), id LIMIT " . self::PER_RUN, [$clientId, self::MAX_ATTEMPTS]);
        $waiting = (int) $this->db->value("SELECT COUNT(*) FROM articles WHERE client_id = ? AND decision = 'mehet' AND uploaded = 0", [$clientId]);
        if (!$rows) {
            $ctx->info($waiting > 0
                ? "{$waiting} Mehet cikk vár, de mind elérte a próbálkozási korlátot vagy már fent volt. A hibát a Havi cikkek oldalon látja."
                : 'Nincs feltöltésre váró Mehet cikk.');
            return ['items' => [], 'stopped' => false];
        }
        $ctx->info(sprintf('%d cikk feltöltése %s', count($rows), $ctx->dry ? 'szárazon (fordítás és állapot-lekérdezés, írás nélkül).' : 'élesben.'));
        $wp = ($this->wpFor)($client);
        $model = (string) ($this->db->value("SELECT value FROM client_settings WHERE client_id = ? AND name = 'modell_feltoltes'", [$clientId]) ?: PressWriter::DEFAULT_MODEL);
        $about = implode(' ', array_map(static fn ($r) => trim((string) $r['value']), $this->db->all("SELECT value FROM post_rules WHERE client_id = ? AND name IN ('Cég','Megoldásterületek') ORDER BY sort", [$clientId])));
        $writer = new PressWriter($this->claude);
        $items = [];
        $stopped = false;
        $errors = [];
        foreach ($rows as $i => $row) {
            if ($i > 0) {
                ($this->sleep)(1000);
            }
            $ref = $row['month'] . '/' . ($row['no'] ?? $row['id']);
            $item = ['id' => (int) $row['id'], 'ref' => $ref, 'title' => $row['title'], 'state' => '', 'hu' => null, 'en' => null, 'url_hu' => '', 'url_en' => '', 'error' => ''];
            try {
                $item = $this->one($client, $row, $wp, $writer, $model, $about, $ctx) + $item;
            } catch (WpException $e) {
                $item['error'] = $e->getMessage();
                $fatal = $e->kind === 'auth' || str_starts_with($e->getMessage(), 'External-link plugin is not applying');
                $this->fail($row, $e, $ctx);
                $item['state'] = $this->stateOf((int) $row['id']);
                $errors[] = $ref . ': ' . $e->getMessage();
                if ($fatal) {
                    $ctx->error('A futás leállt: ' . $e->getMessage());
                    $stopped = true;
                    $items[] = $item;
                    break;
                }
            } catch (\Throwable $e) {
                $item['error'] = $e->getMessage();
                $this->fail($row, $e, $ctx);
                $item['state'] = $this->stateOf((int) $row['id']);
                $errors[] = $ref . ': ' . $e->getMessage();
            }
            $items[] = $item;
        }
        if (!$ctx->dry && ($errors || $stopped)) {
            $this->notify((string) $client['name'], $errors, $stopped);
        }
        return ['items' => $items, 'stopped' => $stopped];
    }

    /**
     * Egy cikk: kulcs → get_pair → (fordítás) → deploy_pair → visszaírás.
     *
     * @param array<string,mixed> $client
     * @param array<string,mixed> $row
     * @return array<string,mixed>
     */
    private function one(array $client, array $row, WpBridgeClient $wp, PressWriter $writer, string $model, string $about, TaskContext $ctx): array
    {
        $ref = $row['month'] . '/' . ($row['no'] ?? $row['id']);
        $source = PressKey::normalizeUrl((string) $row['url']);
        if (!PressKey::acceptable($source)) {
            throw new \InvalidArgumentException('A bővítmény csak https forráslinket fogad el: ' . $row['url']);
        }
        $key = $row['press_key'] !== '' ? (string) $row['press_key'] : PressKey::key((string) $row['url']);

        $pair = $wp->getPair($key);
        if (($pair['state'] ?? '') === 'published') {
            $ctx->info("{$ref}: már kint van ({$key}); csak az adatokat írom vissza.");
            $out = self::fromPosts($pair['posts'] ?? []);
            $ctx->write("{$ref}: visszaírás (kint)", fn () => $this->saveDone((int) $row['id'], $key, $out, null));
            return ['state' => 'kint'] + $out;
        }
        if (($pair['state'] ?? '') === 'conflict') {
            throw new WpException('A pár ütközésben van a WordPressben (conflict); kézi ellenőrzés kell a Beállítások → Xperion sajtószemle oldalon.', 'tool');
        }

        // Fordítás: a korábbi (sikertelen feltöltésnél mentett) szöveget újrahasznosítja, nem fizet érte újra.
        if ($row['hu_title'] !== '' && $row['en_title'] !== '' && trim((string) $row['hu_summary']) !== '' && trim((string) $row['en_summary']) !== '') {
            $tr = ['hu' => ['title' => $row['hu_title'], 'summary' => $row['hu_summary']], 'en' => ['title' => $row['en_title'], 'summary' => $row['en_summary']]];
            $ctx->info("{$ref}: a korábban elkészült fordítást használom.");
        } else {
            if (!$this->claude->hasKey()) {
                throw new \Xperion\Claude\ClaudeException('Nincs beállítva a Claude API-kulcs (config.php: claude_api_key).');
            }
            $text = $this->source->fetch($source);
            $ctx->info("{$ref}: " . ($text !== '' ? 'forrásszöveg letöltve (' . str_word_count($text) . ' szó)' : 'a forrásszöveg nem tölthető le, a címből és a leírásból dolgozom') . ', fordítás: ' . $model . '.');
            $tr = $writer->write((string) $client['name'], (string) $client['website'], $about, [
                'title' => (string) $row['title'], 'date' => (string) ($row['published_on'] ?? ''), 'url' => $source, 'text' => $text, 'description' => (string) ($row['description'] ?? ''),
            ], $model);
            $ctx->write("{$ref}: a fordítás mentése", fn () => $this->db->run('UPDATE articles SET press_key = ?, hu_title = ?, hu_summary = ?, en_title = ?, en_summary = ?, updated_at = ? WHERE id = ?',
                [$key, $tr['hu']['title'], $tr['hu']['summary'], $tr['en']['title'], $tr['en']['summary'], Database::now(), $row['id']]));
        }

        $args = ['key' => $key, 'source_url' => $source, 'date' => self::date($row), 'hu' => $tr['hu'], 'en' => $tr['en']];
        if ($ctx->dry) {
            $ctx->write("{$ref}: deploy_pair ({$key}) – HU: „{$tr['hu']['title']}”, EN: „{$tr['en']['title']}”", static fn () => null);
            return ['state' => 'szaraz', 'hu' => $tr['hu'], 'en' => $tr['en'], 'key' => $key];
        }
        $res = $wp->deployPair($args);
        if (($res['state'] ?? '') !== 'published') {
            throw new WpException('A deploy_pair nem publikált (állapot: ' . ($res['state'] ?? '?') . ').', 'tool');
        }
        $out = self::fromPosts($res['posts'] ?? []);
        $this->saveDone((int) $row['id'], $key, $out, $tr);
        $ctx->done("{$ref}: kint van" . (!empty($res['changed']) ? '' : ' (már korábban kikerült)') . ' – ' . $out['url_hu'] . ' · ' . $out['url_en']);
        return ['state' => 'kint', 'hu' => $tr['hu'], 'en' => $tr['en']] + $out;
    }

    /**
     * @param array<string,mixed> $out
     * @param array<string,mixed>|null $tr
     */
    private function saveDone(int $id, string $key, array $out, ?array $tr): void
    {
        $this->db->run("UPDATE articles SET uploaded = 1, upload_status = 'Feltöltve', wp_state = 'kint', press_key = ?, url_hu = ?, url_en = ?, wp_hu_id = ?, wp_en_id = ?,
            published_at = ?, upload_error = '', updated_at = ? WHERE id = ?", [$key, $out['url_hu'], $out['url_en'], $out['wp_hu_id'], $out['wp_en_id'], Database::now(), Database::now(), $id]);
        if ($tr !== null) {
            $this->db->run('UPDATE articles SET hu_title = ?, hu_summary = ?, en_title = ?, en_summary = ? WHERE id = ?', [$tr['hu']['title'], $tr['hu']['summary'], $tr['en']['title'], $tr['en']['summary'], $id]);
        }
    }

    /** Hiba rögzítése a bővítmény hibatáblája szerint. @param array<string,mixed> $row */
    private function fail(array $row, \Throwable $e, TaskContext $ctx): void
    {
        $ref = $row['month'] . '/' . ($row['no'] ?? $row['id']);
        $msg = $e->getMessage();
        [$state, $count] = match (true) {
            str_starts_with($msg, 'Source URL already exists outside this pair') => ['duplikatum', false],
            str_starts_with($msg, 'Pair locked by a running operation') => ['zarolt', false],
            str_starts_with($msg, 'Publish failed; the other translation may already be live') => ['reszben', true],
            $e instanceof WpException && $e->kind === 'auth' => ['hiba', false],
            default => ['hiba', true],
        };
        $hint = match ($state) {
            'duplikatum' => ' – a cikk már fent van a weboldalon; nem próbálom újra.',
            'zarolt' => ' – a következő futás újrapróbálja.',
            'reszben' => ' – a következő futás befejezi.',
            default => $count && (int) $row['upload_attempts'] + 1 >= self::MAX_ATTEMPTS ? ' – elérte a ' . self::MAX_ATTEMPTS . ' próbálkozást; javítás után a Havi cikkek oldalon indítható újra.' : '',
        };
        $ctx->error("{$ref}: {$msg}{$hint}");
        if ($ctx->dry) {
            return;
        }
        $this->db->run('UPDATE articles SET wp_state = ?, upload_error = ?, upload_attempts = upload_attempts + ?, press_key = IF(press_key = \'\', ?, press_key), updated_at = ? WHERE id = ?',
            [$state, mb_substr($msg, 0, 1000), $count ? 1 : 0, PressKey::acceptable(PressKey::normalizeUrl((string) $row['url'])) ? PressKey::key((string) $row['url']) : '', Database::now(), $row['id']]);
    }

    private function stateOf(int $id): string
    {
        return (string) $this->db->value('SELECT wp_state FROM articles WHERE id = ?', [$id]);
    }

    /** Elakadt cikk újrapróbálásának engedélyezése (a próbálkozások nullázása). */
    public function retry(Access $a, int $articleId): void
    {
        $a->require('ops');
        $cid = $a->clientId() ?? throw new \InvalidArgumentException('Nincs kiválasztott ügyfél.');
        $n = $this->db->run("UPDATE articles SET upload_attempts = 0, wp_state = IF(wp_state = 'duplikatum', 'duplikatum', ''), upload_error = '' WHERE id = ? AND client_id = ? AND uploaded = 0 AND decision = 'mehet'", [$articleId, $cid])->rowCount();
        if ($n !== 1) {
            throw new \InvalidArgumentException('Ez a cikk nem próbálható újra (nincs Mehet jelölése, vagy már kint van).');
        }
        $this->audit->add($cid, $a->userId(), 'Feltöltés újrapróbálása', '#' . $articleId);
    }

    /** @param list<string> $errors */
    private function notify(string $clientName, array $errors, bool $stopped): void
    {
        if (!$this->mailer->configured()) {
            return;
        }
        $ops = array_map(static fn ($r) => (string) $r['email'], $this->db->all("SELECT email FROM users WHERE is_operator = 1 AND status = 'aktiv'"));
        if (!$ops) {
            return;
        }
        $text = ($stopped ? "A feltöltés leállt ({$clientName}). A többi cikk sem ment fel, amíg a hibát nem javítja.\n\n" : "Feltöltési hiba ({$clientName}).\n\n")
            . implode("\n", array_map(static fn ($e) => '- ' . $e, $errors)) . "\n\nRészletek: Vezérlőpult és Havi cikkek.";
        try {
            $this->mailer->send(new Message($ops, ($stopped ? 'Leállt a feltöltés: ' : 'Feltöltési hiba: ') . $clientName, $text));
        } catch (\Throwable) {
            // az értesítés hibája nem írja felül a feltöltés eredményét
        }
    }

    /**
     * @param list<array<string,mixed>> $posts
     * @return array{url_hu:string, url_en:string, wp_hu_id:?int, wp_en_id:?int}
     */
    public static function fromPosts(array $posts): array
    {
        $out = ['url_hu' => '', 'url_en' => '', 'wp_hu_id' => null, 'wp_en_id' => null];
        foreach ($posts as $p) {
            $l = strtolower(substr((string) ($p['language'] ?? ''), 0, 2));
            if ($l === 'hu' || $l === 'en') {
                $out['url_' . $l] = mb_substr((string) ($p['url'] ?? ''), 0, 1000);
                $out['wp_' . $l . '_id'] = isset($p['id']) ? (int) $p['id'] : null;
            }
        }
        return $out;
    }

    /** A bejegyzés dátuma: a cikk megjelenése, de nem jövőbeli (Budapest szerint). @param array<string,mixed> $row */
    private static function date(array $row): string
    {
        $today = (new \DateTimeImmutable('now', new \DateTimeZone('Europe/Budapest')))->format('Y-m-d');
        $d = (string) ($row['published_on'] ?? '');
        if ($d === '' || $d > $today) {
            $last = (new \DateTimeImmutable(sprintf('20%s-%s-01', substr((string) $row['month'], 0, 2), substr((string) $row['month'], 2, 2))))->modify('last day of this month')->format('Y-m-d');
            return min($last, $today);
        }
        return $d;
    }
}
