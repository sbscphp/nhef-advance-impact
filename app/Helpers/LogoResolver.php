<?php

namespace App\Helpers;

/**
 * Resolves which Advance Impact wordmark to embed in an email or PDF: white() (navy/dark
 * backgrounds, e.g. the email header) or colored() (light/white backgrounds, e.g. report, receipt
 * and proposal PDF headers). Both are local, bundled PNGs supplied directly by the user - unlike
 * NHEF Nexus's LogoResolver (main branch), there's no live frontend URL to fetch from: the
 * Advance Impact frontend renders its logo inline rather than serving it as its own asset file.
 */
class LogoResolver
{
    /** @return array{path: string, mime: string, url: string} */
    public static function white(): array
    {
        return self::local('advance-impact-white.png');
    }

    /** @return array{path: string, mime: string, url: string} */
    public static function colored(): array
    {
        return self::local('advance-impact-colored.png');
    }

    /** @return array{path: string, mime: string, url: string} */
    private static function local(string $filename): array
    {
        return [
            'path' => public_path('assets/logo/'.$filename),
            'mime' => 'image/png',
            'url' => rtrim((string) config('app.url'), '/').'/assets/logo/'.$filename,
        ];
    }
}
