<?php
declare(strict_types=1);

namespace Xperion\Posts;

/**
 * A Claude által tervezett SVG szigorú tisztítása: csak rajzoló elemek és attribútumok maradnak,
 * szkript, eseménykezelő, foreignObject és külső hivatkozás nincs. A felület ráadásul <img>-ként
 * (data: URI) jeleníti meg, ahol szkript és külső betöltés amúgy sem fut.
 */
final class SvgSanitizer
{
    private const ELEMENTS = ['svg', 'g', 'defs', 'title', 'desc', 'lineargradient', 'radialgradient', 'stop', 'rect', 'circle', 'ellipse', 'line', 'polyline', 'polygon', 'path',
        'text', 'tspan', 'clippath', 'mask', 'pattern', 'filter', 'fegaussianblur', 'feoffset', 'feblend', 'feflood', 'fecomposite', 'femerge', 'femergenode', 'fedropshadow', 'fecolormatrix', 'use', 'symbol', 'marker'];
    private const ATTRS = ['id', 'class', 'x', 'y', 'x1', 'y1', 'x2', 'y2', 'cx', 'cy', 'r', 'rx', 'ry', 'fx', 'fy', 'width', 'height', 'd', 'points', 'transform', 'viewbox', 'xmlns',
        'fill', 'fill-opacity', 'fill-rule', 'stroke', 'stroke-width', 'stroke-opacity', 'stroke-linecap', 'stroke-linejoin', 'stroke-dasharray', 'stroke-dashoffset', 'stroke-miterlimit', 'opacity',
        'font-family', 'font-size', 'font-weight', 'font-style', 'letter-spacing', 'word-spacing', 'text-anchor', 'dominant-baseline', 'alignment-baseline', 'dx', 'dy', 'rotate', 'textlength', 'lengthadjust',
        'offset', 'stop-color', 'stop-opacity', 'gradientunits', 'gradienttransform', 'spreadmethod', 'patternunits', 'patterntransform', 'clip-path', 'clip-rule', 'mask', 'filter', 'stddeviation',
        'in', 'in2', 'result', 'mode', 'operator', 'flood-color', 'flood-opacity', 'values', 'type', 'preserveaspectratio', 'href', 'xlink:href', 'marker-start', 'marker-mid', 'marker-end',
        'markerwidth', 'markerheight', 'refx', 'refy', 'orient', 'markerunits', 'style', 'role', 'aria-label', 'vector-effect', 'paint-order', 'shape-rendering', 'text-rendering'];

    /** A tisztított SVG, vagy null, ha nem érvényes / nem 1200×1200-as rajz. */
    public static function clean(string $svg): ?string
    {
        $svg = trim($svg);
        if ($svg === '' || strlen($svg) > 400_000 || stripos($svg, '<!DOCTYPE') !== false || stripos($svg, '<!ENTITY') !== false) {
            return null;
        }
        $doc = new \DOMDocument();
        $prev = libxml_use_internal_errors(true);
        $ok = $doc->loadXML($svg, LIBXML_NONET | LIBXML_NOBLANKS);
        libxml_clear_errors();
        libxml_use_internal_errors($prev);
        $root = $doc->documentElement;
        if (!$ok || $root === null || strtolower($root->localName) !== 'svg') {
            return null;
        }
        self::walk($root);
        $root->setAttribute('xmlns', 'http://www.w3.org/2000/svg');
        $root->setAttribute('viewBox', '0 0 1200 1200');
        $root->setAttribute('width', '1200');
        $root->setAttribute('height', '1200');
        $out = $doc->saveXML($root);
        return is_string($out) && strlen($out) > 200 ? $out : null;
    }

    private static function walk(\DOMElement $el): void
    {
        foreach (iterator_to_array($el->childNodes) as $c) {
            if ($c instanceof \DOMElement) {
                if (!in_array(strtolower($c->localName), self::ELEMENTS, true)) {
                    $el->removeChild($c);
                    continue;
                }
                self::walk($c);
            } elseif (!($c instanceof \DOMText)) {
                $el->removeChild($c); // megjegyzés, CDATA, feldolgozási utasítás
            }
        }
        foreach (iterator_to_array($el->attributes) as $a) {
            $name = strtolower($a->nodeName);
            $v = (string) $a->nodeValue;
            $bad = !in_array($name, self::ATTRS, true)
                || (($name === 'href' || $name === 'xlink:href') && !str_starts_with(trim($v), '#'))
                || preg_match('/url\(\s*[\'"]?\s*(?!#)/i', $v)
                || preg_match('/javascript:|expression\(|@import/i', $v);
            if ($bad) {
                $el->removeAttributeNode($a);
            }
        }
    }
}
