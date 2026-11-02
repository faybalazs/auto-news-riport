<?php
declare(strict_types=1);

namespace Xperion\Web;

/** Egyszerű PHP-sablonmegjelenítő; a sablonokban az e() escapel. */
final class View
{
    public function __construct(private string $dir, private string $version)
    {
    }

    /** @param array<string,mixed> $vars */
    public function render(string $template, array $vars = [], ?string $layout = 'layout'): never
    {
        $vars['version'] = $this->version;
        $content = $this->capture($template, $vars);
        echo $layout === null ? $content : $this->capture($layout, $vars + ['content' => $content]);
        exit;
    }

    /** @param array<string,mixed> $vars */
    public function capture(string $template, array $vars): string
    {
        $file = $this->dir . '/' . $template . '.php';
        $e = static fn ($s): string => htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
        $ic = static fn (string $id, string $cls = 'icon icon-sm'): string => '<svg class="' . $cls . '" aria-hidden="true"><use href="#i-' . $id . '"/></svg>';
        extract($vars, EXTR_SKIP);
        ob_start();
        require $file;
        return (string) ob_get_clean();
    }

    /** „2026. október” (az intl bővítmény nélkül is). */
    public static function monthHu(\DateTimeInterface $d): string
    {
        static $m = ['január', 'február', 'március', 'április', 'május', 'június', 'július', 'augusztus', 'szeptember', 'október', 'november', 'december'];
        return $d->format('Y') . '. ' . $m[(int) $d->format('n') - 1];
    }

    /** Felhasználónak szóló dátum (UTC-ből Europe/Budapest). */
    public static function dateHu(?string $utc): string
    {
        if ($utc === null || $utc === '') {
            return '–';
        }
        return (new \DateTimeImmutable($utc, new \DateTimeZone('UTC')))->setTimezone(new \DateTimeZone(date_default_timezone_get()))->format('Y. m. d. H:i');
    }
}
