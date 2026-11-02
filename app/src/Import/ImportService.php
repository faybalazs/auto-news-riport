<?php
declare(strict_types=1);

namespace Xperion\Import;

use Xperion\Auth\Access;
use Xperion\Auth\AuditLog;
use Xperion\Db\Database;
use Xperion\Google\SheetsClient;
use Xperion\Lock;
use Xperion\Log\LocalLog;
use Xperion\Log\LogEntry;
use Xperion\Task\RunResult;
use Xperion\Task\TaskContext;

/**
 * Az aktuális ügyfél adatátvétele a saját Google Sheetjéből (csak üzemeltető),
 * zárolással, futásnaplóval és (élesben) műveleti naplóval.
 */
final class ImportService
{
    /** @param callable(string):SheetsClient $sheetsFor */
    private $sheetsFor;

    public function __construct(private Database $db, callable $sheetsFor, private LocalLog $log, private AuditLog $audit, private string $lockFile)
    {
        $this->sheetsFor = $sheetsFor;
    }

    public function run(Access $a, bool $dry): RunResult
    {
        $a->require('ops');
        $client = $a->client();
        $label = 'Adatátvétel: ' . ($client['name'] ?? '–');
        $start = hrtime(true);
        $now = new \DateTimeImmutable('now');
        $sheetId = $client === null ? '' : (string) $this->db->value('SELECT spreadsheet_id FROM clients WHERE id = ?', [$client['id']]);
        $ctx = new TaskContext($dry, $now, fn (): SheetsClient => ($this->sheetsFor)($sheetId));

        if ($client === null) {
            $ctx->error('Nincs kiválasztott ügyfél.');
        } elseif ($sheetId === '') {
            $ctx->error('Ennél az ügyfélnél nincs megadva Google Sheet. Az ügyfél szerkesztésénél adja meg a tábla linkjét.');
        } elseif (($lock = Lock::acquire($this->lockFile, ['task' => 'atvetel', 'label' => $label, 'dry' => $dry, 'user' => $a->userId(), 'client' => (int) $client['id']])) === null) {
            $ctx->error(Lock::busyMessage($this->lockFile));
        } else {
            $ctx->onLine = [$lock, 'progress'];
            try {
                (new SheetImporter($this->db, $ctx->sheets(), (int) $client['id']))->run($ctx);
            } catch (\Throwable $e) {
                $ctx->error($e->getMessage());
            } finally {
                $lock->release();
            }
        }

        $result = new RunResult('atvetel', $label, $dry, !$ctx->hasErrors(), $ctx->lines(), (int) ((hrtime(true) - $start) / 1_000_000));
        $this->log->append(new LogEntry($now, 'atvetel', $label, $dry, 'admin', $result->ok, $result->summary(), $result->durationMs));
        $this->log->saveResult($result->toArray(), $a->userId(), $client === null ? null : (int) $client['id']);
        if (!$dry && $client !== null) {
            $this->audit->add((int) $client['id'], $a->userId(), $result->ok ? 'Adatátvétel a Google Sheetből' : 'Adatátvétel sikertelen', $result->summary());
        }
        return $result;
    }
}
