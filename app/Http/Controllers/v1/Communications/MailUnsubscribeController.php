<?php

namespace App\Http\Controllers\v1\Communications;

use App\Http\Controllers\Controller;
use App\Services\Communications\MailService;
use Illuminate\Contracts\View\View;

/** Public: opened directly from an email link. The `signed` middleware's signature is the credential, not a Bearer token. */
class MailUnsubscribeController extends Controller
{
    public function __construct(private readonly MailService $mailService) {}

    public function unsubscribe(string $recipientUuid): View
    {
        $this->mailService->unsubscribe($recipientUuid);

        return view('mail.communications.unsubscribed');
    }
}
