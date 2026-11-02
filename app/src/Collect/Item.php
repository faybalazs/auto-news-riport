<?php
declare(strict_types=1);

namespace Xperion\Collect;

/** Egy talált cikk. */
final class Item
{
    public function __construct(
        public readonly string $title,
        public readonly string $url,
        public ?string $date,
        public readonly string $description = '',
    ) {
    }

    /** @return array<string,mixed> */
    public function toArray(): array
    {
        return ['title' => $this->title, 'url' => $this->url, 'date' => $this->date, 'description' => $this->description];
    }
}
