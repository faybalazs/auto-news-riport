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
     * A config.php felső szintjén többször szereplő kulcsok (PHP-ban ilyenkor az alsó érvényes, ami gyakori hiba:
     * a kitöltött rész fölé másolva alul ott marad a minta üres része).
     *
     * @return list<string>
     */
    public function duplicateKeys(): array
    {
        if ($this->source === '' || !is_readable($this->source)) {
            return [];
        }
        $tokens = token_get_all((string) file_get_contents($this->source));
        $depth = 0;
        $seen = [];
        $last = null;
        foreach ($tokens as $t) {
            if (is_string($t)) {
                if ($t === '[' || $t === '(') {
                    $depth++;
                } elseif ($t === ']' || $t === ')') {
                    $depth--;
                }
                $last = null;
                continue;
            }
            if ($t[0] === T_ARRAY) {
                continue;
            }
            if ($t[0] === T_CONSTANT_ENCAPSED_STRING && $depth === 1) {
                $last = trim($t[1], "'\"");
                continue;
            }
            if ($t[0] === T_DOUBLE_ARROW && $last !== null) {
                $seen[$last] = ($seen[$last] ?? 0) + 1;
            }
            if (!in_array($t[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
                $last = $t[0] === T_DOUBLE_ARROW ? null : $last;
            }
        }
        return array_keys(array_filter($seen, static fn ($n) => $n > 1));
    }

    /**
     * Az SMTP-beállítás: a config.php „smtp” része, kiegészítve a felső szintű smtp_* kulcsokkal
     * (ha valaki úgy írta be: 'smtp_host' => …).
     *
     * @return array<string,mixed>
     */
    public function smtp(): array
    {
        $s = $this->get('smtp', []);
        $s = is_array($s) ? $s : [];
        foreach ($this->values as $k => $v) {
            if (is_string($k) && str_starts_with(strtolower($k), 'smtp_') && is_scalar($v) && !isset($s[substr($k, 5)])) {
                $s[substr(strtolower($k), 5)] = $v;
            }
        }
        return $s;
    }

    /**
     * Egy webhely WordPress-belépése a config.php „wordpress” részéből (kulcs: a webhely, pl. 'xperion.hu').
     *
     * @return array{base_url:string, user:string, app_password:string}|null
     */
    public function wordpressSite(string $website): ?array
    {
        $host = self::siteHost($website);
        $all = $this->get('wordpress', []);
        if ($host === '' || !is_array($all)) {
            return null;
        }
        foreach ($all as $k => $v) {
            if (!is_array($v) || self::siteHost((string) $k) !== $host) {
                continue;
            }
            $user = trim((string) ($v['user'] ?? $v['username'] ?? ''));
            $pass = trim((string) ($v['app_password'] ?? $v['password'] ?? ''));
            if ($user === '' || $pass === '') {
                return null;
            }
            $base = trim((string) ($v['base_url'] ?? ''));
            return ['base_url' => rtrim($base !== '' ? $base : 'https://' . strtolower(trim((string) $k)), '/'), 'user' => $user, 'app_password' => $pass];
        }
        return null;
    }

    /** A beállított WordPress-webhelyek (kitöltött belépéssel). @return list<string> */
    public function wordpressSites(): array
    {
        $all = $this->get('wordpress', []);
        return is_array($all) ? array_values(array_filter(array_map('strval', array_keys($all)), fn ($k) => $this->wordpressSite($k) !== null)) : [];
    }

    /** „https://www.Xperion.hu/” → „xperion.hu” */
    public static function siteHost(string $s): string
    {
        $s = strtolower(trim($s));
        $h = parse_url(str_contains($s, '://') ? $s : 'https://' . $s, PHP_URL_HOST);
        return is_string($h) ? (string) preg_replace('/^www\./', '', $h) : '';
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
            ['label' => 'Konfigurációs fájl', 'ok' => $this->isLoaded() && !($dup = $this->duplicateKeys()), 'note' => !$this->isLoaded() ? 'hiányzik' : ($dup ? basename($this->source) . ': kétszer szerepel: ' . implode(', ', $dup) . ' (az alsó érvényes – töröld a fölöslegeset)' : basename($this->source))],
            ['label' => 'Adatbázis', 'ok' => $this->has('db.name') && $this->has('db.user'), 'note' => $this->has('db.name') ? 'beállítva' : 'hiányzik'],
            ['label' => 'Beállítókulcs', 'ok' => strlen($adminKey) >= 16, 'note' => $adminKey === '' ? 'hiányzik' : (strlen($adminKey) < 16 ? 'túl rövid' : 'beállítva')],
            ['label' => 'Cron-kulcs', 'ok' => strlen($cronKey) >= 16, 'note' => $cronKey === '' ? 'hiányzik' : (strlen($cronKey) < 16 ? 'túl rövid' : 'beállítva')],
            ['label' => 'Google (adatátvételhez)', 'ok' => $this->serviceAccountJson() !== '', 'note' => $this->serviceAccountJson() !== '' ? 'beállítva' : 'hiányzik'],
            ['label' => 'Claude API-kulcs', 'ok' => $this->has('claude_api_key'), 'note' => $this->has('claude_api_key') ? 'beállítva' : 'a havi futáshoz kell'],
            ['label' => 'WordPress (feltöltés)', 'ok' => ($wp = $this->wordpressSites()) !== [], 'note' => $wp ? implode(', ', $wp) : 'a feltöltéshez kell'],
            ['label' => 'SMTP (e-mail)', 'ok' => ($sp = \Xperion\Mail\SmtpMailer::problems($this->smtp())) === [], 'note' => $sp === [] ? 'beállítva' : implode('; ', $sp) . (in_array('smtp', $this->duplicateKeys(), true) ? ' – a config.php-ban kétszer szerepel az „smtp” rész, és az alsó (üres) érvényes: töröld' : '')],
        ];
    }
}
