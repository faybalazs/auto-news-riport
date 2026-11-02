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
