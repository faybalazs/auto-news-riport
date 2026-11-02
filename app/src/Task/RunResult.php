<?php
declare(strict_types=1);

namespace Xperion\Task;

final class RunResult
{
    /** @param list<array{level:string, text:string}> $lines */
    public function __construct(
        public readonly string $task,
        public readonly string $label,
        public readonly bool $dry,
        public readonly bool $ok,
        public readonly array $lines,
        public readonly int $durationMs,
    ) {
    }

    /** Rövid összefoglaló a naplóba: az első hiba, különben az utolsó sor. */
    public function summary(): string
    {
        foreach ($this->lines as $l) {
            if ($l['level'] === 'error') {
                return mb_substr($l['text'], 0, 500);
            }
        }
        $last = array_key_last($this->lines);
        return $last === null ? '' : mb_substr($this->lines[$last]['text'], 0, 500);
    }

    /** @return array<string,mixed> */
    public function toArray(): array
    {
        return [
            'task' => $this->task,
            'label' => $this->label,
            'dry' => $this->dry,
            'ok' => $this->ok,
            'lines' => $this->lines,
            'duration_ms' => $this->durationMs,
        ];
    }

    /** Sima szöveg a cron-kimenethez. */
    public function toText(): string
    {
        $prefix = ['info' => '  ', 'ok' => '✓ ', 'warn' => '! ', 'error' => '✗ ', 'dry' => '~ '];
        $out = $this->label . ($this->dry ? ' (szárazfuttatás)' : '') . ': ' . ($this->ok ? 'OK' : 'HIBA') . ' · ' . $this->durationMs . " ms\n";
        foreach ($this->lines as $l) {
            $out .= ($prefix[$l['level']] ?? '  ') . $l['text'] . "\n";
        }
        return $out;
    }
}
