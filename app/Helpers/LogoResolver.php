<?php

namespace App\Helpers;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Resolves which NHEF wordmark to embed. green() tries the live SVG, falling back to a bundled PNG
 * if unreachable. white() always uses the PNG since most webmail clients strip embedded SVG.
 */
class LogoResolver
{
    private const CACHE_TTL_MINUTES = 30;

    private const GREEN_LOGO_URL = 'https://nhef-nexus-fe-client.vercel.app/assets/nhefLogoGreen-ilv-z_gy.svg';

    /** @return array{path: string, mime: string, url: string} */
    public static function white(): array
    {
        $path = public_path('assets/logo/nhef-nexus-white.png');

        return [
            'path' => $path,
            'mime' => 'image/png',
            'url' => rtrim((string) config('app.url'), '/').'/assets/logo/nhef-nexus-white.png',
        ];
    }

    /** @return array{path: string, mime: string, url: string} */
    public static function green(): array
    {
        return self::resolve(
            self::GREEN_LOGO_URL,
            public_path('assets/logo/nhef-nexus-green.png'),
            rtrim((string) config('app.url'), '/').'/assets/logo/nhef-nexus-green.png',
            'green',
        );
    }

    /** @return array{path: string, mime: string, url: string} */
    private static function resolve(string $remoteUrl, string $localFallbackPath, string $localFallbackUrl, string $cacheKey): array
    {
        $cachedSvgPath = storage_path('app/logo-cache/'.$cacheKey.'.svg');

        $isLive = Cache::remember("logo_live.{$cacheKey}", now()->addMinutes(self::CACHE_TTL_MINUTES), function () use ($remoteUrl, $cachedSvgPath) {
            try {
                $response = Http::timeout(3)->get($remoteUrl);

                if ($response->successful() && str_contains((string) $response->header('Content-Type'), 'svg')) {
                    File::ensureDirectoryExists(dirname($cachedSvgPath));
                    File::put($cachedSvgPath, $response->body());

                    return true;
                }
            } catch (Throwable) {
                // Network error, timeout, or the frontend's asset hash moved on - fall back below.
            }

            return false;
        });

        if ($isLive && File::exists($cachedSvgPath)) {
            return ['path' => $cachedSvgPath, 'mime' => 'image/svg+xml', 'url' => $remoteUrl];
        }

        return ['path' => $localFallbackPath, 'mime' => 'image/png', 'url' => $localFallbackUrl];
    }
}
