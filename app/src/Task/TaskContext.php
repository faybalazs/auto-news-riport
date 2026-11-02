<?php
declare(strict_types=1);

namespace Xperion\Task;

use Xperion\Google\SheetsClient;

/**
 * Egy futás környezete: szárazfuttatás-jelző, idő, Sheets, és a futás
 * magyar nyelvű jegyzőkönyve (ezt látja a felhasználó).
 */
final class TaskContext
{
    /** @var list<array{level:string, text:string}> */
    private array $lines = [];
    /** @var (callable(string):void)|null minden új sor (pl. a zárolófájlba, hogy a felület lássa a lépést) */
    public $onLine = null;

    /** @var callable():SheetsClient */
    private $sheetsFactory;
    private ?SheetsClient $sheets = null;

    public function __construct(
        public readonly bool $dry,
        public readonly \DateTimeImmutable $now,
        callable $sheetsFactory,
    ) {
        $this->sheetsFactory = $sheetsFactory;
    }

    public function sheets(): SheetsClient
    {
        return $this->sheets ??= ($this->sheetsFactory)();
    }

    public function info(string $text): void
    {
        $this->lines[] = ['level' => 'info', 'text' => $text];
        $this->emit($text);
    }

    public function done(string $text): void
    {
        $this->lines[] = ['level' => 'ok', 'text' => $text];
        $this->emit($text);
    }

    public function warn(string $text): void
    {
        $this->lines[] = ['level' => 'warn', 'text' => $text];
        $this->emit($text);
    }

    public function error(string $text): void
    {
        $this->lines[] = ['level' => 'error', 'text' => $text];
        $this->emit($text);
    }

    /** Írási művelet: szárazon csak leírja, élesben végrehajtja. */
    public function write(string $description, callable $action): void
    {
        if ($this->dry) {
            $this->lines[] = ['level' => 'dry', 'text' => 'Élesben ezt tenné: ' . $description];
        $this->emit('Élesben ezt tenné: ' . $description);
            return;
        }
        $action();
        $this->done($description);
    }

    private function emit(string $text): void
    {
        if ($this->onLine !== null) {
            try {
                ($this->onLine)($text);
            } catch (\Throwable) {
            }
        }
    }

    /** @return list<array{level:string, text:string}> */
    public function lines(): array
    {
        return $this->lines;
    }

    public function hasErrors(): bool
    {
        foreach ($this->lines as $l) {
            if ($l['level'] === 'error') {
                return true;
            }
        }
        return false;
    }
}
