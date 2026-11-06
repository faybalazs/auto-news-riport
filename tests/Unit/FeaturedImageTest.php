<?php
declare(strict_types=1);

namespace Xperion\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Xperion\Press\FeaturedImage;
use Xperion\Tests\FakeWeb;

final class FeaturedImageTest extends TestCase
{
    private static function png(int $w, int $h): string
    {
        $im = imagecreatetruecolor($w, $h);
        imagefill($im, 0, 0, (int) imagecolorallocate($im, 20, 60, 140));
        ob_start();
        imagepng($im);
        imagedestroy($im);
        return (string) ob_get_clean();
    }

    public function testFindsImageInPluginOrderAndResolvesRelativeUrls(): void
    {
        $html = '<html><head><meta name="twitter:image" content="https://cdn.test/tw.jpg"><meta property="og:image" content="/img/og?w=1200"></head></html>';
        self::assertSame('https://src.test/img/og?w=1200', FeaturedImage::findInHtml($html, 'https://src.test/blog/cikk'));
        self::assertSame('https://cdn.test/tw.jpg', FeaturedImage::findInHtml('<meta name="twitter:image" content="https://cdn.test/tw.jpg">', 'https://src.test/a'));
        self::assertSame('', FeaturedImage::findInHtml('<p>nincs kép</p>', 'https://src.test/a'));
    }

    public function testConvertsToJpegUnderCleanUrl(): void
    {
        $dir = sys_get_temp_dir() . '/xp-kep-' . bin2hex(random_bytes(4));
        $web = new FakeWeb([
            'https://src.test/cikk' => '<meta property="og:image" content="https://cdn.test/kep?format=auto">',
            'https://cdn.test/kep?format=auto' => self::png(640, 360),
        ]);
        $fi = new FeaturedImage($web, $dir, 'https://app.test/xperion/');
        $url = $fi->prepare('https://src.test/cikk');
        self::assertMatchesRegularExpression('~^https://app\.test/xperion/kepek/[0-9a-f]{40}\.jpg$~', $url);
        $info = getimagesize($dir . '/' . basename($url));
        self::assertSame([640, 360, IMAGETYPE_JPEG], [$info[0], $info[1], $info[2]]);
        // második hívásnál nem tölti le újra
        $n = count($web->requested);
        $fi->prepare('https://src.test/cikk');
        self::assertCount($n + 1, $web->requested, 'csak a forrásoldal, a kép nem');

        $small = new FakeWeb(['https://src.test/k' => '<meta property="og:image" content="https://cdn.test/k.png">', 'https://cdn.test/k.png' => self::png(120, 80)]);
        try {
            (new FeaturedImage($small, $dir, 'https://app.test/'))->prepare('https://src.test/k');
            self::fail('túl kicsi kép');
        } catch (\RuntimeException $e) {
            self::assertStringContainsString('túl kicsi', $e->getMessage());
        }
        self::assertStringContainsString('https', (new FeaturedImage($web, $dir, 'http://app.test/'))->unavailableReason());
    }
}
