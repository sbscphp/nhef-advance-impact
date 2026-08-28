<?php

namespace App\Jobs;

use App\Enums\ProspectMessageStatusEnum;
use App\Mail\ProspectMessageMail;
use App\Models\ProspectMessage;
use App\Services\Theme\ThemeResolver;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/** Sends one composed prospect message and marks it sent; see ProspectService::composeMessage(). */
class SendProspectMessageJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public readonly string $prospectMessageUuid) {}

    public function handle(ThemeResolver $themeResolver): void
    {
        $message = ProspectMessage::query()
            ->with(['prospect'])
            ->where('uuid', $this->prospectMessageUuid)
            ->first();

        if (! $message instanceof ProspectMessage || $message->prospect === null) {
            return;
        }

        try {
            $theme = $themeResolver->resolveForMail();

            Mail::to($message->prospect->email)->send(new ProspectMessageMail(
                $message->prospect->fullName(),
                $theme,
                $message->subject,
                $message->body,
            ));

            $message->forceFill([
                'status' => ProspectMessageStatusEnum::SENT->value,
                'sent_at' => now(),
            ])->save();
        } catch (\Throwable $th) {
            Log::warning('Prospect message email failed.', [
                'prospect_message_uuid' => $message->uuid,
                'exception' => $th::class,
                'message' => $th->getMessage(),
            ]);
        }
    }
}
