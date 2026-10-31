<?php

namespace App\Services;

use DOMDocument;
use DOMElement;
use DOMNode;

class WebsiteHtmlSanitizer
{
    /** @var array<int, string> */
    private const ALLOWED_TAGS = [
        'p', 'br', 'strong', 'b', 'em', 'i', 'u', 's', 'ul', 'ol', 'li',
        'h2', 'h3', 'h4', 'blockquote', 'a', 'hr', 'table', 'thead', 'tbody',
        'tr', 'th', 'td', 'code', 'pre', 'span', 'img', 'figure', 'figcaption',
    ];

    /** @var array<int, string> */
    private const DANGEROUS_TAGS = [
        'script', 'style', 'iframe', 'object', 'embed', 'form', 'input',
        'button', 'textarea', 'select', 'option', 'svg', 'math', 'link',
        'meta', 'base', 'video', 'audio', 'source', 'track',
    ];

    /** @var array<string, array<int, string>> */
    private const ALLOWED_ATTRIBUTES = [
        'a' => ['href', 'title', 'target', 'rel', 'style'],
        'img' => ['src', 'alt', 'title', 'width', 'height', 'loading', 'style'],
        'th' => ['colspan', 'rowspan', 'style'],
        'td' => ['colspan', 'rowspan', 'style'],
        'span' => ['style'],
        'p' => ['style'],
        'h2' => ['style'],
        'h3' => ['style'],
        'h4' => ['style'],
        'blockquote' => ['style'],
        'ul' => ['style'],
        'ol' => ['style'],
        'li' => ['style'],
        'table' => ['style'],
        'thead' => ['style'],
        'tbody' => ['style'],
        'tr' => ['style'],
        'code' => ['style'],
        'pre' => ['style'],
        'figure' => ['style'],
        'figcaption' => ['style'],
    ];

    /**
     * Inline CSS declarations that may survive saving. The rich text editor
     * uses these for image sizing, alignment and spacing, so they must be kept
     * or every resize would be lost on the next save. Position, background
     * shorthands and anything that can load a URL are deliberately excluded.
     *
     * @var array<int, string>
     */
    private const ALLOWED_STYLES = [
        'width', 'height', 'max-width', 'max-height', 'min-width', 'min-height',
        'aspect-ratio', 'object-fit', 'object-position',
        'text-align', 'text-decoration', 'vertical-align', 'float', 'clear',
        'margin', 'margin-top', 'margin-right', 'margin-bottom', 'margin-left',
        'padding', 'padding-top', 'padding-right', 'padding-bottom', 'padding-left',
        'border', 'border-width', 'border-style', 'border-color', 'border-radius',
        'font-size', 'font-weight', 'font-style', 'line-height', 'letter-spacing',
        'color', 'background-color',
    ];

    /**
     * Return a conservative, balanced HTML fragment. Unknown tags are unwrapped
     * and active content is removed entirely. Inline styles are limited to a
     * small layout whitelist (for image sizing and alignment); event handler
     * attributes never survive.
     */
    public function sanitize(?string $html): string
    {
        $html = trim((string) $html);

        if ($html === '') {
            return '';
        }

        $document = new DOMDocument('1.0', 'UTF-8');
        $previous = libxml_use_internal_errors(true);
        $encoded = mb_encode_numericentity($html, [0x80, 0x10FFFF, 0, 0x1FFFFF], 'UTF-8');
        $wrapped = '<div id="sanitizer-root">'.$encoded.'</div>';
        $document->loadHTML($wrapped, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $root = (new \DOMXPath($document))->query('//*[@id="sanitizer-root"]')?->item(0);
        if (! $root) {
            return '';
        }

        $this->walk($root);

        $output = '';
        foreach ($root->childNodes as $child) {
            $output .= $document->saveHTML($child);
        }

        return trim($output);
    }

    public function plainText(?string $html): string
    {
        return trim(preg_replace('/\s+/', ' ', strip_tags((string) $html)) ?? '');
    }

    private function walk(DOMNode $node): void
    {
        for ($child = $node->firstChild; $child !== null;) {
            $next = $child->nextSibling;

            if ($child instanceof DOMElement) {
                $tag = strtolower($child->tagName);

                if (in_array($tag, self::DANGEROUS_TAGS, true)) {
                    $node->removeChild($child);
                    $child = $next;

                    continue;
                }

                if (! in_array($tag, self::ALLOWED_TAGS, true)) {
                    $this->unwrap($child);
                    $child = $next;

                    continue;
                }

                $this->cleanAttributes($child, $tag);
                $this->walk($child);
            }

            $child = $next;
        }
    }

    private function unwrap(DOMElement $element): void
    {
        $parent = $element->parentNode;
        if (! $parent) {
            return;
        }

        while ($element->firstChild) {
            $parent->insertBefore($element->firstChild, $element);
        }

        $parent->removeChild($element);
    }

    private function cleanAttributes(DOMElement $element, string $tag): void
    {
        $allowed = self::ALLOWED_ATTRIBUTES[$tag] ?? [];

        for ($i = $element->attributes->length - 1; $i >= 0; $i--) {
            $attribute = $element->attributes->item($i);
            if (! $attribute) {
                continue;
            }

            $name = strtolower($attribute->name);
            if (! in_array($name, $allowed, true)) {
                $element->removeAttribute($attribute->name);

                continue;
            }

            if ($name === 'style') {
                $safe = $this->safeStyle($attribute->value);
                if ($safe === '') {
                    $element->removeAttribute($attribute->name);
                } else {
                    $element->setAttribute('style', $safe);
                }

                continue;
            }

            if (in_array($name, ['href', 'src'], true) && ! $this->safeUrl($attribute->value)) {
                $element->removeAttribute($attribute->name);
            }
        }

        if ($tag === 'a') {
            if (! $element->hasAttribute('href')) {
                $this->unwrap($element);

                return;
            }

            if (strtolower($element->getAttribute('target')) === '_blank') {
                $element->setAttribute('rel', 'noopener noreferrer');
            }
        }

        if ($tag === 'img') {
            if (! $element->hasAttribute('src')) {
                $element->parentNode?->removeChild($element);

                return;
            }

            $element->setAttribute('loading', 'lazy');
        }
    }

    /**
     * Keep only a small, explicitly safe subset of inline CSS. Declarations
     * are rebuilt from scratch so unknown properties, url() references and
     * CSS expressions can never reach the public site.
     */
    private function safeStyle(string $style): string
    {
        $declarations = [];

        foreach (explode(';', $style) as $declaration) {
            if (! str_contains($declaration, ':')) {
                continue;
            }

            [$property, $value] = explode(':', $declaration, 2);
            $property = strtolower(trim($property));
            $value = trim($value);

            if ($value === '' || ! in_array($property, self::ALLOWED_STYLES, true)) {
                continue;
            }

            // Only plain CSS values: no calls, escapes, markup or comments.
            if (! preg_match('/^[a-zA-Z0-9\s.,%#()+\-*\/]+$/', $value)) {
                continue;
            }

            if (preg_match('/expression|javascript|vbscript|url\s*\(|@import|behavior|\\\\|[<>{}]/i', $value)) {
                continue;
            }

            $declarations[] = $property.': '.$value;
        }

        return implode('; ', $declarations);
    }

    private function safeUrl(string $url): bool
    {
        $url = trim($url);

        if ($url === '' || str_starts_with($url, '#') || str_starts_with($url, '/')) {
            return $url !== '';
        }

        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));

        return in_array($scheme, ['http', 'https', 'mailto', 'tel'], true);
    }
}
