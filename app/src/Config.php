<?php
declare(strict_types=1);

namespace Xperion;

final class Config
{
    /** @param array<string,mixed> $values */
    public function __construct(private array $values, private string $source = '')
    {
    }

    /**
     * Elsőként a webgyökér fölötti "xperion-config.php", utána az alkalmazás
     * mappájában lévő "config.php".
     *
     * @param list<string> $candidates
     */
    public static function load(array $candidates): self
    {
        foreach ($candidates as $path) {
            if (is_file($path)) {
                $values = require $path;
                if (!is_array($values)) {
                    throw new \RuntimeException('A konfigurációs fájl nem tömböt ad vissza: ' . basename($path));
                }
                return new self($values, $path);
            }
        }
        return new self([], '');
    }

    public function source(): string
    {
        return $this->source;
    }

    public function isLoaded(): bool
    {
        return $this->source !== '';
    }

    /** Pontozott kulcs: "smtp.host". */
    public function get(string $key, mixed $default = null): mixed
    {
        $value = $this->values;
        foreach (explode('.', $key) as $part) {
            if (!is_array($value) || !array_key_exists($part, $value)) {
                return $default;
            }
            $value = $value[$part];
        }
        return $value;
    }

    public function string(string $key, string $default = ''): string
    {
        $v = $this->get($key, $default);
        return is_scalar($v) ? trim((string) $v) : $default;
    }

    public function has(string $key): bool
    {
        return $this->string($key) !== '';
    }

    /** A felületről feltöltött kulcs helye (a storage mappában); ha létezik, elsőbbséget élvez a config.php beállításával szemben. */
    private string $uploadedKeyFile = '';

    public function setUploadedKeyFile(string $path): void
    {
        $this->uploadedKeyFile = $path;
    }

    /**
     * A szolgáltatásfiók JSON-ja, vagy üres szöveg. Sorrend: a felületről feltöltött kulcs →
     * config.php JSON-szöveg → config.php fájlútvonal (relatív útvonal a config.php mappájához képest).
     */
    public function serviceAccountJson(): string
    {
        if ($this->uploadedKeyFile !== '' && is_readable($this->uploadedKeyFile)) {
            return (string) file_get_contents($this->uploadedKeyFile);
        }
        $inline = $this->string('google_service_account_json');
        if ($inline !== '') {
            return $inline;
        }
        if (($f = $this->configKeyFile()) !== null) {
            return (string) file_get_contents($f);
        }
        return '';
    }

    /** Honnan jön a Google-kulcs (a felületen megjelenítendő szöveg). */
    public function serviceAccountSource(): string
    {
        return match (true) {
            $this->uploadedKeyFile !== '' && is_readable($this->uploadedKeyFile) => 'feltöltve a felületen',
            $this->string('google_service_account_json') !== '' => 'config.php (JSON-szöveg)',
            $this->configKeyFile() !== null => 'config.php (fájl)',
            $this->string('google_service_account_file') !== '' => 'a config.php-ban megadott fájl nem található',
            default => 'nincs',
        };
    }

    /** A config.php-ban megadott kulcsfájl valódi helye (relatív útvonal a config.php mappájához képest), vagy null. */
    private function configKeyFile(): ?string
    {
        $file = $this->string('google_service_account_file');
        if ($file === '') {
            return null;
        }
        foreach ([$file, $this->source !== '' ? dirname($this->source) . '/' . ltrim($file, '/') : ''] as $f) {
            if ($f !== '' && is_file($f) && is_readable($f)) {
                return $f;
            }
        }
        return null;
    }

    /**
     * Beállítások állapota az admin felülethez (értéket nem ad ki).
     *
     * @return list<array{label:string, ok:bool, note:string}>
     */
    public function checklist(): array
    {
        $adminKey = $this->string('admin_key');
        $cronKey = $this->string('cron_key');
        return [
            ['label' => 'Konfigurációs fájl', 'ok' => $this->isLoaded(), 'note' => $this->isLoaded() ? basename($this->source) : 'hiányzik'],
            ['label' => 'Adatbázis', 'ok' => $this->has('db.name') && $this->has('db.user'), 'note' => $this->has('db.name') ? 'beállítva' : 'hiányzik'],
            ['label' => 'Beállítókulcs', 'ok' => strlen($adminKey) >= 16, 'note' => $adminKey === '' ? 'hiányzik' : (strlen($adminKey) < 16 ? 'túl rövid' : 'beállítva')],
            ['label' => 'Cron-kulcs', 'ok' => strlen($cronKey) >= 16, 'note' => $cronKey === '' ? 'hiányzik' : (strlen($cronKey) < 16 ? 'túl rövid' : 'beállítva')],
            ['label' => 'Google (adatátvételhez)', 'ok' => $this->serviceAccountJson() !== '', 'note' => $this->serviceAccountJson() !== '' ? 'beállítva' : 'hiányzik'],
            ['label' => 'Claude API-kulcs', 'ok' => $this->has('claude_api_key'), 'note' => $this->has('claude_api_key') ? 'beállítva' : 'a havi futáshoz kell'],
            ['label' => 'SMTP', 'ok' => $this->has('smtp.host') && $this->has('smtp.from_email'), 'note' => $this->has('smtp.host') ? 'beállítva' : 'a havi e-mailhez kell'],
        ];
    }
}
