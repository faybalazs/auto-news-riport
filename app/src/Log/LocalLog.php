<?php
declare(strict_types=1);

namespace Xperion\Log;

/**
 * Helyi napló JSON-soros fájlban. Minden futás ide ír (a szárazfuttatás is);
 * az admin felület innen olvas, így a Sheets elérhetetlensége sem vakít el.
 */
final class LocalLog
{
    public function __construct(private string $file, private int $keep = 2000)
    {
    }

    public function append(LogEntry $entry): void
    {
        $line = json_encode($entry->toArray(), JSON_UNESCAPED_UNICODE) . "\n";
        file_put_contents($this->file, $line, FILE_APPEND | LOCK_EX);
        if (filesize($this->file) > 1_500_000) {
            $this->trim();
        }
    }

    /** @return list<LogEntry> a legfrissebb elöl */
    public function recent(int $limit = 500): array
    {
        if (!is_file($this->file)) {
            return [];
        }
        $lines = file($this->file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
        $out = [];
        foreach (array_reverse(array_slice($lines, -$limit)) as $line) {
            $a = json_decode($line, true);
            if (is_array($a)) {
                $out[] = LogEntry::fromArray($a);
            }
        }
        return $out;
    }

    /** A megadott napnál régebbi futások törlése (megőrzés: 90 nap). */
    public function prune(int $days = 90): int
    {
        if (!is_file($this->file)) {
            return 0;
        }
        $limit = (new \DateTimeImmutable('-' . $days . ' days'))->getTimestamp();
        $lines = file($this->file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
        $keep = array_values(array_filter($lines, static function ($l) use ($limit) {
            $a = json_decode($l, true);
            return !is_array($a) || strtotime((string) ($a['time'] ?? '')) === false || strtotime((string) $a['time']) >= $limit;
        }));
        if (count($keep) !== count($lines)) {
            file_put_contents($this->file, $keep ? implode("\n", $keep) . "\n" : '', LOCK_EX);
        }
        return count($lines) - count($keep);
    }

    /**
     * A futás teljes eredményének mentése (a felület akkor is megmutatja, ha közben elnavigáltak).
     *
     * @param array<string,mixed> $result RunResult::toArray()
     */
    public function saveResult(array $result, ?int $userId = null, ?int $clientId = null): void
    {
        $file = dirname($this->file) . '/eredmeny-' . preg_replace('/[^a-z0-9_-]/', '', (string) ($result['task'] ?? 'x')) . '.json';
        unset($result['data']['items']); // a hosszú listák nem kellenek az összefoglalóhoz
        @file_put_contents($file, (string) json_encode($result + ['finished_at' => time(), 'user' => $userId, 'client' => $clientId], JSON_UNESCAPED_UNICODE), LOCK_EX);
    }

    /** @return list<array<string,mixed>> a feladatok legutóbbi mentett eredményei, a legfrissebb elöl */
    public function results(): array
    {
        $out = [];
        foreach (glob(dirname($this->file) . '/eredmeny-*.json') ?: [] as $f) {
            $r = json_decode((string) file_get_contents($f), true);
            if (is_array($r)) {
                $out[] = $r;
            }
        }
        usort($out, static fn ($a, $b) => ($b['finished_at'] ?? 0) <=> ($a['finished_at'] ?? 0));
        return $out;
    }

    /** Az adott feladat legutóbbi futása. */
    public function last(string $task): ?LogEntry
    {
        foreach ($this->recent() as $e) {
            if ($e->task === $task) {
                return $e;
            }
        }
        return null;
    }

    private function trim(): void
    {
        $lines = file($this->file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
        file_put_contents($this->file, implode("\n", array_slice($lines, -$this->keep)) . "\n", LOCK_EX);
    }
}
