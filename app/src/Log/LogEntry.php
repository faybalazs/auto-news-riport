<?php
declare(strict_types=1);

namespace Xperion\Log;

final class LogEntry
{
    public function __construct(
        public readonly \DateTimeImmutable $time,
        public readonly string $task,
        public readonly string $label,
        public readonly bool $dry,
        public readonly string $trigger,
        public readonly bool $ok,
        public readonly string $details,
        public readonly int $durationMs,
    ) {
    }

    /** @return array<string,mixed> */
    public function toArray(): array
    {
        return [
            'time' => $this->time->format(DATE_ATOM),
            'task' => $this->task,
            'label' => $this->label,
            'dry' => $this->dry,
            'trigger' => $this->trigger,
            'ok' => $this->ok,
            'details' => $this->details,
            'duration_ms' => $this->durationMs,
        ];
    }

    /** Magyar formátumú időpont a szerver időzónájában (Europe/Budapest). */
    public function timeHu(): string
    {
        return $this->time->setTimezone(new \DateTimeZone(date_default_timezone_get()))->format('Y. m. d. H:i');
    }

    /** @param array<string,mixed> $a */
    public static function fromArray(array $a): self
    {
        return new self(
            new \DateTimeImmutable((string) ($a['time'] ?? 'now')),
            (string) ($a['task'] ?? ''),
            (string) ($a['label'] ?? ''),
            (bool) ($a['dry'] ?? false),
            (string) ($a['trigger'] ?? ''),
            (bool) ($a['ok'] ?? false),
            (string) ($a['details'] ?? ''),
            (int) ($a['duration_ms'] ?? 0),
        );
    }

    /** Sor a Napló lapra: Időbélyeg · Feladat · Eredmény · Részletek. */
    public function toSheetRow(): array
    {
        return [
            $this->time->format('Y.m.d. H:i:s'),
            $this->label . ' (' . $this->trigger . ')',
            $this->ok ? 'OK' : 'Hiba',
            $this->details,
        ];
    }
}
