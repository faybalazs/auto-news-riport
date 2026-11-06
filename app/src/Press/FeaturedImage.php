<?php
declare(strict_types=1);

namespace Xperion\Press;

use Xperion\Collect\Html;
use Xperion\Http\HttpClient;

/**
 * A forráscikk kiemelt képe, ha a WordPress a bővítmény saját képimportját elutasítja (pl. „Sorry, you are not allowed
 * to upload this file type.”: a kép címének nincs rendes kiterjesztése, vagy a formátumát a webhely nem engedi).
 * A program maga keresi meg a képet (ugyanabban a sorrendben, mint a bővítmény), JPEG-re alakítja, és a saját
 * `kepek/` mappájában, rendes `.jpg` végű címen teszi közzé; a bővítmény ezt a címet importálja (featured_image_url).
 */
final class FeaturedImage
{
    public const META = ['og:image:secure_url', 'og:image', 'og:image:url', 'twitter:image', 'twitter:image:src'];
    public const MAX_BYTES = 15_000_000;
    public const MAX_WIDTH = 2000;
    public const KEEP_DAYS = 60;

    /**
     * @param string $dir a nyilvános képmappa (pl. <alkalmazás>/kepek)
     * @param string $baseUrl az alkalmazás nyilvános címe perjellel a végén (https kell, a bővítmény csak ilyet fogad el)
     */
    public function __construct(private HttpClient $http, private string $dir, private string $baseUrl)
    {
    }

    /** Elérhető-e a funkció ezen a szerveren (GD, írható mappa, https-cím). */
    public function unavailableReason(): string
    {
        return match (true) {
            !function_exists('imagecreatefromstring') || !function_exists('imagejpeg') => 'a szerver PHP-jából hiányzik a GD képkezelő bővítmény',
            !str_starts_with($this->baseUrl, 'https://') => 'az alkalmazás címe nem https (config.php: app_url)',
            !is_dir($this->dir) && !@mkdir($this->dir, 0755, true) => 'a kepek mappa nem hozható létre',
            !is_writable($this->dir) => 'a kepek mappa nem írható',
            default => '',
        };
    }

    /** A kép címe a forrásoldal metacímkéiből (a bővítmény sorrendjében); üres, ha nincs. */
    public static function findInHtml(string $html, string $pageUrl): string
    {
        if (trim($html) === '') {
            return '';
        }
        $x = Html::dom($html);
        $found = [];
        foreach ($x->query('//meta') ?: [] as $m) {
            if (!$m instanceof \DOMElement) {
                continue;
            }
            $key = strtolower(trim($m->getAttribute('property') ?: $m->getAttribute('name')));
            $val = trim($m->getAttribute('content'));
            if ($val !== '' && in_array($key, self::META, true) && !isset($found[$key])) {
                $found[$key] = $val;
            }
        }
        foreach (self::META as $k) {
            if (isset($found[$k])) {
                return self::absolute($found[$k], $pageUrl);
            }
        }
        return '';
    }

    /**
     * A forráscikk képének letöltése, JPEG-re alakítása és közzététele. A visszatérési érték a kép nyilvános címe.
     *
     * @throws \RuntimeException érthető magyar okkal, ha nem sikerül
     */
    public function prepare(string $sourceUrl): string
    {
        if (($why = $this->unavailableReason()) !== '') {
            throw new \RuntimeException('A kép nem alakítható át: ' . $why . '.');
        }
        $page = $this->http->request('GET', $sourceUrl, ['Accept' => 'text/html,application/xhtml+xml']);
        $img = $page->ok() ? self::findInHtml($page->body, $page->url !== '' ? $page->url : $sourceUrl) : '';
        if ($img === '') {
            throw new \RuntimeException('A forráscikk oldalán nincs kép (og:image).');
        }
        $name = sha1($img) . '.jpg';
        $path = $this->dir . '/' . $name;
        if (!is_file($path)) {
            $r = $this->http->request('GET', $img, ['Accept' => 'image/*']);
            if (!$r->ok() || $r->body === '' || strlen($r->body) > self::MAX_BYTES) {
                throw new \RuntimeException('A forráscikk képe nem tölthető le (' . $img . ').');
            }
            $this->toJpeg($r->body, $path);
        }
        $this->cleanup();
        return $this->baseUrl . 'kepek/' . $name;
    }

    private function toJpeg(string $bytes, string $path): void
    {
        $src = @imagecreatefromstring($bytes);
        if ($src === false) {
            throw new \RuntimeException('A forráscikk képének formátumát a szerver nem tudja átalakítani.');
        }
        $w = imagesx($src);
        $h = imagesy($src);
        if ($w < 200 || $h < 100) {
            imagedestroy($src);
            throw new \RuntimeException("A forráscikk képe túl kicsi ({$w}×{$h}, legalább 200×100 kell).");
        }
        $nw = min($w, self::MAX_WIDTH);
        $nh = (int) round($h * $nw / $w);
        $dst = imagecreatetruecolor($nw, $nh);
        imagefill($dst, 0, 0, (int) imagecolorallocate($dst, 255, 255, 255)); // átlátszó háttér helyett fehér
        imagecopyresampled($dst, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);
        $tmp = $path . '.tmp';
        $ok = imagejpeg($dst, $tmp, 85);
        imagedestroy($src);
        imagedestroy($dst);
        if (!$ok || !@rename($tmp, $path)) {
            @unlink($tmp);
            throw new \RuntimeException('A JPEG-kép nem menthető a kepek mappába.');
        }
    }

    /** A régi képek törlése (a bővítmény importálás után a saját médiatárában tárolja őket). */
    private function cleanup(): void
    {
        $limit = time() - self::KEEP_DAYS * 86400;
        foreach (glob($this->dir . '/*.jpg') ?: [] as $f) {
            if (@filemtime($f) < $limit) {
                @unlink($f);
            }
        }
    }

    private static function absolute(string $url, string $base): string
    {
        if (preg_match('~^https?://~i', $url)) {
            return $url;
        }
        $p = parse_url($base);
        if (!is_array($p) || empty($p['host'])) {
            return $url;
        }
        $origin = ($p['scheme'] ?? 'https') . '://' . $p['host'] . (isset($p['port']) ? ':' . $p['port'] : '');
        if (str_starts_with($url, '//')) {
            return ($p['scheme'] ?? 'https') . ':' . $url;
        }
        if (str_starts_with($url, '/')) {
            return $origin . $url;
        }
        $dir = preg_replace('~/[^/]*$~', '/', $p['path'] ?? '/') ?: '/';
        return $origin . $dir . $url;
    }
}
