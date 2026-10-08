<?php

namespace App\Support;

/**
 * Allow-list cleaner for the rich-text editor content (headings, emphasis, lists, links, alignment).
 * Everything else is stripped, and only http(s)/mailto links survive on anchors.
 */
final class HtmlSanitizer
{
    private const ALLOWED_TAGS = '<p><br><strong><b><em><i><u><s><strike><sub><sup><h1><h2><h3><h4><ul><ol><li><blockquote><code><pre><hr><a>';

    public static function clean(?string $html): ?string
    {
        if ($html === null || trim($html) === '') {
            return null;
        }

        $clean = strip_tags($html, self::ALLOWED_TAGS);

        // Drop every attribute except a safe href on anchors.
        $clean = preg_replace_callback('/<(\/?)([a-z0-9]+)([^>]*)>/i', function (array $match): string {
            [, $closing, $tag, $attributes] = $match;

            if ($closing === '/' || strtolower($tag) !== 'a') {
                return '<'.$closing.strtolower($tag).'>';
            }

            if (preg_match('/href\s*=\s*"((?:https?:\/\/|mailto:)[^"]*)"/i', $attributes, $href)) {
                return '<a href="'.htmlspecialchars($href[1], ENT_QUOTES).'" rel="noopener noreferrer">';
            }

            return '<a>';
        }, $clean);

        return trim((string) $clean) === '' ? null : trim((string) $clean);
    }
}
