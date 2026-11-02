<?php
declare(strict_types=1);

namespace Xperion\Task;

use Xperion\Google\SheetsClient;
use Xperion\Lock;
use Xperion\Log\LocalLog;
use Xperion\Log\LogEntry;

/**
 * Feladat futtatása zárolással, hibafogással és naplózással.
 * Egy feladat hibája nem dobódik tovább: a jegyzőkönyvbe és a naplóba kerül.
 */
final class Runner
{
    /** @var array<string,Task> */
    private array $tasks = [];
    /** @var callable():SheetsClient */
    private $sheetsFactory;
    /** @var callable():\DateTimeImmutable */
    private $clock;
    /** @var (callable(RunResult, string):void)|null hibás futás után (értesítés az üzemeltetőnek) */
    public $onFailure = null;

    /** @param list<Task> $tasks */
    public function __construct(
        array $tasks,
        callable $sheetsFactory,
        private LocalLog $log,
        private string $lockFile,
        ?callable $clock = null,
    ) {
        foreach ($tasks as $t) {
            $this->tasks[$t->id()] = $t;
        }
        $this->sheetsFactory = $sheetsFactory;
        $this->clock = $clock ?? static fn (): \DateTimeImmutable => new \DateTimeImmutable('now');
    }

    /** @return array<string,Task> */
    public function tasks(): array
    {
        return $this->tasks;
    }

    public function has(string $id): bool
    {
        return isset($this->tasks[$id]);
    }

    public function run(string $id, bool $dry, string $trigger): RunResult
    {
        $task = $this->tasks[$id] ?? throw new \InvalidArgumentException('Ismeretlen feladat: ' . $id);
        $start = hrtime(true);
        $now = ($this->clock)();
        $ctx = new TaskContext($dry, $now, $this->sheetsFactory);

        $lock = Lock::acquire($this->lockFile);
        if ($lock === null) {
            $ctx->error('Egy másik futás folyamatban van, ez a futás kimarad. Próbáld újra pár perc múlva.');
        } else {
            try {
                $task->run($ctx);
            } catch (\Throwable $e) {
                $ctx->error($e->getMessage());
            } finally {
                $lock->release();
            }
        }

        $result = new RunResult($id, $task->label(), $dry, !$ctx->hasErrors(), $ctx->lines(), (int) ((hrtime(true) - $start) / 1_000_000));
        $entry = new LogEntry($now, $id, $task->label(), $dry, $trigger, $result->ok, $result->summary(), $result->durationMs);
        $this->log->append($entry);

        if (!$result->ok && $this->onFailure !== null) {
            try {
                ($this->onFailure)($result, $trigger);
            } catch (\Throwable) {
                // az értesítés hibája nem írja felül a futás eredményét
            }
        }
        return $result;
    }
}
