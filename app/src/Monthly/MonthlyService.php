<?php
declare(strict_types=1);

namespace Xperion\Monthly;

use Xperion\Auth\Access;
use Xperion\Auth\AuditLog;
use Xperion\Claude\ClaudeClient;
use Xperion\Collect\CollectService;
use Xperion\Db\Database;
use Xperion\Import\Url;
use Xperion\Lock;
use Xperion\Log\LocalLog;
use Xperion\Log\LogEntry;
use Xperion\Task\RunResult;
use Xperion\Task\TaskContext;
use Xperion\Web\View;

/**
 * Havi futás egy ügyfélnek (product.md 6.1): gyűjtés → Claude-értékelés →
 * (élesben) a hónap cikklistája és vezetői összefoglalója az adatbázisba.
 * Idempotens: ha a hónapnak már van listája, nem készít újat.
 */
final class MonthlyService
{
    public const DEFAULT_EVAL_MODEL = 'claude-haiku-5-5';
    public const DEFAULT_SUMMARY_MODEL = 'claude-sonnet-5-5';

    public function __construct(
        private Database $db,
        private CollectService $collect,
        private ClaudeClient $claude,
        private LocalLog $log,
        private AuditLog $audit,
        private string $lockFile,
        private ?\Closure $afterSave = null,
    ) {
    }

    /** Kézi futtatás a felületről (üzemeltető, az aktuális ügyfélre). */
    public function runForAccess(Access $a, string $month, bool $dry): RunResult
    {
        $a->require('ops');
        $client = $a->client();
        $label = 'Havi futás: ' . ($client['name'] ?? '–') . ' (' . $month . ')';
        $start = hrtime(true);
        $now = new \DateTimeImmutable('now');
        $ctx = new TaskContext($dry, $now, static fn () => throw new \LogicException('nincs Sheets'));
        $data = [];
        if ($client === null) {
            $ctx->error('Nincs kiválasztott ügyfél.');
        } elseif (($lock = Lock::acquire($this->lockFile, ['task' => 'havi', 'label' => $label, 'dry' => $dry, 'user' => $a->userId(), 'client' => (int) $client['id']])) === null) {
            $ctx->error(Lock::busyMessage($this->lockFile));
        } else {
            $ctx->onLine = [$lock, 'progress'];
            try {
                $data = $this->run((int) $client['id'], $month, $ctx);
            } catch (\Throwable $e) {
                $ctx->error($e->getMessage());
            } finally {
                $lock->release();
            }
        }
        $result = new RunResult('havi', $label, $dry, !$ctx->hasErrors(), $ctx->lines(), (int) ((hrtime(true) - $start) / 1_000_000), $data);
        $this->log->append(new LogEntry($now, 'havi', $label, $dry, 'admin', $result->ok, $result->summary(), $result->durationMs));
        $this->log->saveResult($result->toArray(), $a->userId(), $client === null ? null : (int) $client['id']);
        if (!$dry && $client !== null) {
            $this->audit->add((int) $client['id'], $a->userId(), $result->ok ? 'Havi futás' : 'Havi futás sikertelen', $month . ' · ' . $result->summary());
        }
        return $result;
    }

    /**
     * A futás magja (a zárolást és a naplót a hívó intézi).
     *
     * @return array<string,mixed> {items, summary, stats}
     */
    public function run(int $clientId, string $month, TaskContext $ctx): array
    {
        // Meglévőnek számít a hónap, ha van összefoglalója, vagy már vannak cikkei (pl. a Sheetből átvett hónap).
        if ($this->db->value('SELECT 1 FROM month_reports WHERE client_id = ? AND month = ?', [$clientId, $month])
            || $this->db->value('SELECT 1 FROM articles WHERE client_id = ? AND month = ? LIMIT 1', [$clientId, $month])) {
            $ctx->info("A(z) {$month} havi lista már elkészült; új listát nem készítek.");
            return ['items' => [], 'summary' => null, 'stats' => null, 'exists' => true];
        }
        if (!$this->claude->hasKey()) {
            throw new \Xperion\Claude\ClaudeException('Nincs beállítva a Claude API-kulcs (config.php: claude_api_key). A havi futáshoz kell; a „Csak gyűjtés” nélküle is működik.');
        }
        $settings = [];
        foreach ($this->db->all("SELECT name, value FROM client_settings WHERE client_id = ? AND name IN ('modell_ertekeles','modell_poszt')", [$clientId]) as $s) {
            $settings[$s['name']] = trim((string) $s['value']);
        }
        $evalModel = ($settings['modell_ertekeles'] ?? '') ?: self::DEFAULT_EVAL_MODEL;
        $sumModel = ($settings['modell_poszt'] ?? '') ?: self::DEFAULT_SUMMARY_MODEL;
        $clientName = (string) $this->db->value('SELECT name FROM clients WHERE id = ?', [$clientId]);
        [$from] = CollectService::monthRange($month);
        $monthLabel = View::monthHu(new \DateTimeImmutable($from));

        $collected = $this->collect->collectForClient($clientId, $month, $ctx);
        $items = $collected['items'];
        $before = $this->claude->usage;
        if ($items) {
            $ctx->info(sprintf('Értékelés: %d cikk, %s modellel (%d cikk/kérés).', count($items), $evalModel, Evaluator::BATCH));
            $eval = (new Evaluator($this->db, $this->claude))->evaluate($clientId, $items, $evalModel);
            foreach ($items as $i => &$it) {
                $it += ['desc' => $eval[$i]['desc'], 'recommendation' => $eval[$i]['recommendation'], 'reason' => $eval[$i]['reason']];
            }
            unset($it);
            $by = array_count_values(array_column($items, 'recommendation'));
            $ctx->info('Javaslatok: ' . implode(', ', array_map(static fn ($k) => $k . ': ' . ($by[$k] ?? 0), Evaluator::RECOMMENDATIONS)) . '.');
            $summary = (new Summarizer($this->claude))->summarize($clientName, $monthLabel, $items, $sumModel);
            $ctx->info('Vezetői összefoglaló elkészült (' . $sumModel . ').');
        } else {
            $summary = ['rows' => [['label' => 'Cikkek száma', 'value' => '0']], 'details' => 'Ebben a hónapban a figyelt forrásokon nem jelent meg új cikk.'];
        }
        $used = ['input' => $this->claude->usage['input'] - $before['input'], 'output' => $this->claude->usage['output'] - $before['output'], 'cache_read' => $this->claude->usage['cache_read'] - $before['cache_read']];
        $stats = ['items' => count($items), 'sources' => count($collected['sources']), 'tokens' => $used];
        if ($used['input'] > 0) {
            $ctx->info(sprintf('Claude: %s bemeneti és %s kimeneti token (gyorsítótárból: %s).', number_format($used['input'], 0, ',', ' '), number_format($used['output'], 0, ',', ' '), number_format($used['cache_read'], 0, ',', ' ')));
        }

        $ctx->write(sprintf('A(z) %s lista mentése: %d cikk és a vezetői összefoglaló.', $month, count($items)), function () use ($clientId, $month, $items, $summary, $stats, $evalModel): void {
            $this->db->transaction(function () use ($clientId, $month, $items, $summary, $stats, $evalModel): void {
                $now = Database::now();
                foreach (array_values($items) as $n => $it) {
                    $this->db->run('INSERT IGNORE INTO articles (client_id, month, no, source_name, published_on, title, description, url, url_hash, recommendation, reason, evaluated_by, from_new_source, imported, created_at, updated_at)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0, ?, ?)', [
                        $clientId, $month, $n + 1, mb_substr((string) $it['source'], 0, 190), $it['date'], mb_substr((string) $it['title'], 0, 500), $it['desc'],
                        mb_substr((string) $it['url'], 0, 1000), Url::hash((string) $it['url']), $it['recommendation'], $it['reason'], $evalModel, (int) ($it['new_source'] ?? 0), $now, $now,
                    ]);
                }
                $this->db->insert('month_reports', ['client_id' => $clientId, 'month' => $month, 'summary_rows' => json_encode($summary['rows'], JSON_UNESCAPED_UNICODE), 'details' => $summary['details'], 'stats' => json_encode($stats), 'model' => $evalModel, 'created_at' => $now]);
            });
        });
        if ($this->afterSave !== null) {
            if ($ctx->dry) {
                $ctx->info('Élesben ezután a havi e-mail is elkészülne (jóváhagyásra vár, az ügyfél nem kapja meg).');
            } else {
                try {
                    ($this->afterSave)($clientId, $month, $ctx);
                } catch (\Throwable $e) {
                    $ctx->warn('A havi lista elkészült, de a havi e-mail nem: ' . $e->getMessage() . ' A Vezérlőpultról újra generálható.');
                }
            }
        }
        return ['items' => $items, 'summary' => $summary, 'stats' => $stats, 'exists' => false];
    }
}
