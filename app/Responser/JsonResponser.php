<?php

namespace App\Responser;

use App\Http\Middleware\RequestResponseEncryptionMiddleware;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\App;

class JsonResponser
{
    /**
     * Whether encrypted API responses may include a cleartext "preview" field in the outer
     * envelope. Disabled in production regardless of config; only allowed environments opt in.
     *
     * @see RequestResponseEncryptionMiddleware::encryptOutboundResponse()
     */
    public static function encryptedResponsePreviewEnabled(): bool
    {
        if (App::isProduction() || App::environment(['production', 'prod'])) {
            return false;
        }

        if (! config('security.api_encryption.response_preview')) {
            return false;
        }

        return App::environment(['local', 'dev', 'development', 'staging', 'testing']);
    }

    public static function sendPaginated(
        int $status,
        $data = [],
        string $message = ''
    ): JsonResponse {
        $data = $data->toArray();
        $response = [
            'status' => $status,
            'data' => $data['data'],
            'meta' => $data['meta'],
            'message' => ucwords($message),
        ];

        return response()->json($response, $status);
    }

    public static function send(
        bool $error = true,
        string $message = '',
        $data = [],
        $statusCode = 200,
        $th = null
    ): JsonResponse {
        return response()->json([
            'error' => $error,
            'message' => $message,
            'data' => $data,
        ], $statusCode);
    }
}
