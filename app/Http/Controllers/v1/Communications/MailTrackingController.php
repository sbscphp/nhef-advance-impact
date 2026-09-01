<?php

namespace App\Http\Controllers\v1\Communications;

use App\Http\Controllers\Controller;
use App\Services\Communications\MailService;
use Symfony\Component\HttpFoundation\Response;

/** Public: hit by the `<img>` tag embedded in a sent mail. The `signed` middleware's signature is the credential. */
class MailTrackingController extends Controller
{
    /** A 1x1 transparent GIF, the smallest valid open-tracking pixel. */
    private const TRANSPARENT_PIXEL_BASE64 = 'R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBTAA7';

    public function __construct(private readonly MailService $mailService) {}

    public function track(string $mailUuid, string $recipientUuid): Response
    {
        $this->mailService->trackOpen($recipientUuid);

        return new Response(base64_decode(self::TRANSPARENT_PIXEL_BASE64), 200, [
            'Content-Type' => 'image/gif',
            'Cache-Control' => 'no-store, no-cache, must-revalidate',
        ]);
    }
}
