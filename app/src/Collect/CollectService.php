<?php
declare(strict_types=1);

namespace Xperion\Collect;

use Xperion\Auth\Access;
use Xperion\Db\Database;
use Xperion\Import\Url;
use Xperion\Lock;
use Xperion\Log\LocalLog;
use Xperion\Log\LogEntry;
use Xperion\Task\RunResult;
use Xperion\Task\TaskContext;

/**
 * Havi cikkgyűjtés egy ügyfélnek: aktív források → a hónap cikkei, a már
 * meglévő (korábbi hónapokban szereplő) és a kizáró kulcsszavas cikkek nélkül.
 * Ebben a lépésben nem ír az adatbázisba (a mentés és az értékelés az 5. lépés).
 */
final class CollectService
{
    public function __construct(private Database $db, private Collector $collector, private LocalLog $log, private string $lockFile)
    {
    }

    /** Az előző hónap kulcsa (ÉÉHH) egy időponthoz. */
    public static function previousMonth(\DateTimeImmutable $now): string
    {
        return $now->modify('first day of previous month')->format('ym');
    }

    /** @return array{0:string,1:string} [első nap, utolsó nap] Y-m-d */
    public static function monthRange(string $key): array
    {
        if (!preg_match('/^(\d{2})(0[1-9]|1[0-2])$/', $key, $m)) {
            throw new \InvalidArgumentException('Érvénytelen hónap: ' . $key);
        }
        $first = new \DateTimeImmutable(sprintf('20%s-%s-01', $m[1], $m[2]));
        return [$first->format('Y-m-d'), $first->modify('last day of this month')->format('Y-m-d')];
    }

    /** Kézi futtatás a felületről (üzemeltető, az aktuális ügyfélre). */
    public function run(Access $a, string $month): RunResult
    {
        $a->require('ops');
        $client = $a->client();
        $label = 'Cikkgyűjtés: ' . ($client['name'] ?? '–') . ' (' . $month . ')';
        $start = hrtime(true);
        $ctx = new TaskContext(true, new \DateTimeImmutable('now'), static fn () => throw new \LogicException('nincs Sheets'));
        $data = ['sources' => [], 'items' => []];
        if ($client === null) {
            $ctx->error('Nincs kiválasztott ügyfél.');
        } elseif (($lock = Lock::acquire($this->lockFile, ['task' => 'gyujtes', 'label' => $label, 'dry' => true, 'user' => $a->userId(), 'client' => (int) $client['id']])) === null) {
            $ctx->error(Lock::busyMessage($this->lockFile));
        } else {
            $ctx->onLine = [$lock, 'progress'];
            try {
                $data = $this->collectForClient((int) $client['id'], $month, $ctx);
            } catch (\Throwable $e) {
                $ctx->error($e->getMessage());
            } finally {
                $lock->release();
            }
        }
        $result = new RunResult('gyujtes', $label, true, !$ctx->hasErrors(), $ctx->lines(), (int) ((hrtime(true) - $start) / 1_000_000), $data);
        $this->log->append(new LogEntry(new \DateTimeImmutable('now'), 'gyujtes', $label, true, 'admin', $result->ok, $result->summary(), $result->durationMs));
        $this->log->saveResult($result->toArray(), $a->userId(), $client === null ? null : (int) $client['id']);
        return $result;
    }

    /**
     * @return array{sources: list<array<string,mixed>>, items: list<array<string,mixed>>}
     */
    public function collectForClient(int $clientId, string $month, TaskContext $ctx): array
    {
        [$from, $to] = self::monthRange($month);
        $sources = $this->db->all('SELECT name, type, url, is_new FROM sources WHERE client_id = ? AND active = 1 ORDER BY name', [$clientId]);
        if (!$sources) {
            $ctx->warn('Nincs aktív forrás. Vegye át a forrásokat a Google Sheetből (Ügyfelek → Adatátvétel).');
            return ['sources' => [], 'items' => []];
        }
        $exclude = array_column($this->db->all('SELECT keyword FROM keywords WHERE client_id = ? AND is_exclude = 1', [$clientId]), 'keyword');
        $known = array_flip(array_column($this->db->all('SELECT url_hash FROM articles WHERE client_id = ?', [$clientId]), 'url_hash'));
        $ctx->info(sprintf('Hónap: %s – %s · %d aktív forrás · %d kizáró kulcsszó · %d korábbi cikk a duplikátumszűréshez.', $from, $to, count($sources), count($exclude), count($known)));

        $seen = [];
        $outSources = [];
        $items = [];
        foreach ($sources as $s) {
            $r = $this->collector->collect((string) $s['url'], (string) $s['type']);
            $st = ['name' => $s['name'], 'url' => $s['url'], 'method' => $r['method'], 'error' => $r['error'], 'found' => count($r['items']), 'in_month' => 0, 'undated' => 0, 'duplicate' => 0, 'excluded' => 0, 'kept' => 0];
            foreach ($r['items'] as $it) {
                if ($it->date === null) {
                    $st['undated']++;
                    continue;
                }
                if ($it->date < $from || $it->date > $to) {
                    continue;
                }
                $st['in_month']++;
                $h = Url::hash($it->url);
                if (isset($known[$h]) || isset($seen[$h])) {
                    $st['duplicate']++;
                    continue;
                }
                if (($kw = self::excludedBy($it->title . ' ' . $it->description, $exclude)) !== null) {
                    $st['excluded']++;
                    continue;
                }
                $seen[$h] = true;
                $st['kept']++;
                $items[] = $it->toArray() + ['source' => $s['name'], 'new_source' => (int) $s['is_new']];
            }
            $outSources[] = $st;
            $line = sprintf('%s (%s): %d talált, %d a hónapban, %d duplikátum, %d kizárt, %d marad%s.', $s['name'] ?: $s['url'], $r['method'], $st['found'], $st['in_month'], $st['duplicate'], $st['excluded'], $st['kept'], $st['undated'] ? ", {$st['undated']} dátum nélkül" : '');
            $r['error'] !== null ? $ctx->warn($line . ' ' . $r['error']) : $ctx->info($line);
        }
        usort($items, static fn ($a, $b) => [$a['new_source'], $a['date']] <=> [$b['new_source'], $b['date']]);
        $ctx->done(sprintf('Összesen %d új cikk a hónapban (%d forrásból). Mentés és értékelés az 5. lépésben; most nem került semmi az adatbázisba.', count($items), count($sources)));
        return ['sources' => $outSources, 'items' => $items];
    }

    /** A kizáró kulcsszó, ha a szövegben egész szóként szerepel. @param list<string> $keywords */
    public static function excludedBy(string $text, array $keywords): ?string
    {
        foreach ($keywords as $kw) {
            $kw = trim($kw);
            if ($kw !== '' && preg_match('/(?<![\p{L}\p{N}])' . preg_quote($kw, '/') . '(?![\p{L}\p{N}])/iu', $text)) {
                return $kw;
            }
        }
        return null;
    }
}
