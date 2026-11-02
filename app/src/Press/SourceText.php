<?php
declare(strict_types=1);

namespace Xperion\Press;

use Xperion\Collect\Html;
use Xperion\Http\HttpClient;

/** A forráscikk főszövege a fordításhoz (article → main → a törzs bekezdései), legfeljebb ~6000 szó. */
final class SourceText
{
    public const MAX_WORDS = 6000;

    public function __construct(private HttpClient $http)
    {
    }

    /** Üres szöveg, ha a cikk nem tölthető le vagy nincs benne olvasható szöveg. */
    public function fetch(string $url): string
    {
        try {
            $r = $this->http->request('GET', $url, ['Accept' => 'text/html,application/xhtml+xml']);
        } catch (\Throwable) {
            return '';
        }
        return $r->ok() ? self::extract($r->body) : '';
    }

    public static function extract(string $html): string
    {
        if (trim($html) === '') {
            return '';
        }
        $x = Html::dom($html);
        foreach ($x->query('//script|//style|//noscript|//nav|//footer|//header|//aside|//form') ?: [] as $n) {
            $n->parentNode?->removeChild($n);
        }
        foreach (['//article', '//main', '//*[@role="main"]', '//body'] as $q) {
            $root = $x->query($q)?->item(0);
            if ($root === null) {
                continue;
            }
            $parts = [];
            foreach ($x->query('.//h1|.//h2|.//h3|.//p|.//li', $root) ?: [] as $n) {
                $t = trim(preg_replace('/\s+/u', ' ', Html::text($n)) ?? '');
                if (mb_strlen($t) >= 3) {
                    $parts[] = $t;
                }
            }
            $text = implode("\n", array_unique($parts));
            if (str_word_count(strip_tags($text)) >= 60 || $q === '//body') {
                $words = preg_split('/\s+/u', $text) ?: [];
                return count($words) > self::MAX_WORDS ? implode(' ', array_slice($words, 0, self::MAX_WORDS)) : $text;
            }
        }
        return '';
    }
}
