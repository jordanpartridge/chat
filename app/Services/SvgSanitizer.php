<?php

declare(strict_types=1);

namespace App\Services;

class SvgSanitizer
{
    /**
     * Dangerous tags that should be removed from SVG content.
     *
     * @var list<string>
     */
    private const DANGEROUS_TAGS = [
        'script',
        'foreignObject',
        'iframe',
        'object',
        'embed',
        'link',
        'style',
    ];

    /**
     * Dangerous attributes that should be removed (event handlers, etc.).
     *
     * @var list<string>
     */
    private const DANGEROUS_ATTRIBUTES = [
        'onload',
        'onerror',
        'onclick',
        'onmouseover',
        'onmouseout',
        'onmousemove',
        'onfocus',
        'onblur',
        'onchange',
        'onsubmit',
        'onreset',
        'onkeydown',
        'onkeypress',
        'onkeyup',
        'onabort',
        'ondblclick',
        'onmousedown',
        'onmouseup',
        'onresize',
        'onscroll',
        'onunload',
        'onanimationstart',
        'onanimationend',
        'onanimationiteration',
        'ontransitionend',
    ];

    /**
     * Sanitize SVG content by removing dangerous elements and attributes.
     */
    public function sanitize(string $svg): string
    {
        // Remove script tags and their content
        foreach (self::DANGEROUS_TAGS as $tag) {
            $svg = preg_replace(
                '/<'.$tag.'\b[^>]*>.*?<\/'.$tag.'>/is',
                '',
                $svg
            ) ?? $svg;
            // Also remove self-closing versions
            $svg = preg_replace(
                '/<'.$tag.'\b[^>]*\/?>/is',
                '',
                $svg
            ) ?? $svg;
        }

        // Remove event handler attributes
        foreach (self::DANGEROUS_ATTRIBUTES as $attr) {
            $svg = preg_replace(
                '/\s'.$attr.'\s*=\s*["\'][^"\']*["\']/is',
                '',
                $svg
            ) ?? $svg;
        }

        // Remove javascript: and data: URLs in href/xlink:href attributes
        $svg = preg_replace(
            '/\b(href|xlink:href)\s*=\s*["\']?\s*(javascript|data):[^"\'>\s]*/is',
            '',
            $svg
        ) ?? $svg;

        // Remove any remaining javascript: protocol references
        $svg = preg_replace(
            '/javascript\s*:/is',
            '',
            $svg
        ) ?? $svg;

        return $svg;
    }
}
