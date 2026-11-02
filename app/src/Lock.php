<?php
declare(strict_types=1);

namespace Xperion;

/** Fájlzár, hogy két futás ne fusson egymásra (flock, nem blokkoló). */
final class Lock
{
    /** @param resource $handle */
    private function __construct(private $handle)
    {
    }

    public static function acquire(string $path): ?self
    {
        $h = @fopen($path, 'c');
        if ($h === false) {
            throw new \RuntimeException('A zárolófájl nem hozható létre: ' . basename($path));
        }
        if (!flock($h, LOCK_EX | LOCK_NB)) {
            fclose($h);
            return null;
        }
        ftruncate($h, 0);
        fwrite($h, (string) getmypid());
        return new self($h);
    }

    public function release(): void
    {
        if (is_resource($this->handle)) {
            flock($this->handle, LOCK_UN);
            fclose($this->handle);
        }
    }

    public function __destruct()
    {
        $this->release();
    }
}
