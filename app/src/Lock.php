<?php
declare(strict_types=1);

namespace Xperion;

/**
 * Fájlzár, hogy két futás ne fusson egymásra (flock, nem blokkoló). A zárolófájlban a futás adatai
 * (mi fut, ki indította, mikor, az aktuális lépés) is ott vannak, így a felület ki tudja írni.
 */
final class Lock
{
    /** @var array<string,mixed> */
    private array $meta;

    /** @param resource $handle @param array<string,mixed> $meta */
    private function __construct(private $handle, array $meta)
    {
        $this->meta = $meta;
    }

    /** @param array<string,mixed> $meta task, label, dry, user (azonosító), trigger */
    public static function acquire(string $path, array $meta = []): ?self
    {
        $h = @fopen($path, 'c+');
        if ($h === false) {
            throw new \RuntimeException('A zárolófájl nem hozható létre: ' . basename($path));
        }
        if (!flock($h, LOCK_EX | LOCK_NB)) {
            fclose($h);
            return null;
        }
        $lock = new self($h, $meta + ['pid' => getmypid(), 'started_at' => time(), 'step' => '']);
        $lock->write();
        return $lock;
    }

    /** Az aktuális lépés rögzítése (a TaskContext minden sorát ide is írja). */
    public function progress(string $step): void
    {
        $this->meta['step'] = mb_substr($step, 0, 300);
        $this->meta['updated_at'] = time();
        $this->write();
    }

    private function write(): void
    {
        if (is_resource($this->handle)) {
            ftruncate($this->handle, 0);
            rewind($this->handle);
            fwrite($this->handle, (string) json_encode($this->meta, JSON_UNESCAPED_UNICODE));
            fflush($this->handle);
        }
    }

    /**
     * A folyamatban lévő futás adatai, vagy null, ha nincs futás.
     *
     * @return array<string,mixed>|null
     */
    public static function current(string $path): ?array
    {
        if (!is_file($path)) {
            return null;
        }
        $h = @fopen($path, 'r');
        if ($h === false) {
            return null;
        }
        if (flock($h, LOCK_SH | LOCK_NB)) {
            flock($h, LOCK_UN); // senki nem tartja: nincs futás
            fclose($h);
            return null;
        }
        $meta = json_decode((string) stream_get_contents($h), true);
        fclose($h);
        $meta = is_array($meta) ? $meta : [];
        $meta['elapsed'] = isset($meta['started_at']) ? max(0, time() - (int) $meta['started_at']) : null;
        return $meta;
    }

    /** Érthető üzenet, ha már fut valami. */
    public static function busyMessage(string $path): string
    {
        $m = self::current($path);
        if ($m === null || empty($m['label'])) {
            return 'Egy másik futás folyamatban van. Próbálja újra pár perc múlva.';
        }
        return 'Már fut: ' . $m['label'] . (!empty($m['dry']) ? ' (szárazon)' : '') . ', ' . self::duration((int) ($m['elapsed'] ?? 0)) . ' óta'
            . (!empty($m['step']) ? ' – most: ' . $m['step'] : '') . '. Megvárhatja a Vezérlőpulton; a végén itt is megnézheti az eredményt.';
    }

    public static function duration(int $s): string
    {
        return $s < 60 ? $s . ' mp' : intdiv($s, 60) . ' perc ' . ($s % 60) . ' mp';
    }

    public function release(): void
    {
        if (is_resource($this->handle)) {
            ftruncate($this->handle, 0);
            flock($this->handle, LOCK_UN);
            fclose($this->handle);
        }
    }

    public function __destruct()
    {
        $this->release();
    }
}
