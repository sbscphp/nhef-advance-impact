<?php

namespace App\Http\Controllers\v1\Admin\Communications;

use App\Helpers\GeneralHelper;
use App\Helpers\PDFReportHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Communications\CommunicationsListRequest;
use App\Http\Requests\Admin\Communications\CreateMailRequest;
use App\Http\Requests\Admin\Communications\MailDashboardRequest;
use App\Http\Requests\Admin\Communications\MailListRequest;
use App\Http\Requests\Admin\Communications\MailRecipientListRequest;
use App\Http\Requests\Admin\Communications\SendMailRequest;
use App\Http\Requests\Admin\Communications\UpdateMailRequest;
use App\Http\Resources\Communications\EmailUnsubscribeResource;
use App\Http\Resources\Communications\MailRecipientResource;
use App\Http\Resources\Communications\MailResource;
use App\Models\Admin;
use App\Models\EmailUnsubscribe;
use App\Models\Mail as MailCampaign;
use App\Models\MailRecipient;
use App\Responser\JsonResponser;
use App\Services\Communications\MailService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Pagination\LengthAwarePaginator;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Audience is resolved into `mail_recipients` only on send; sending is always queued, never synchronous. */
class MailController extends Controller
{
    public function __construct(
        private readonly MailService $mailService,
        private readonly PDFReportHelper $pdfReportHelper,
    ) {}

    public function index(MailListRequest $request)
    {
        try {
            $listing = $request->validated();
            $export = $listing['export'] ?? null;

            return match ($export) {
                'csv' => $this->respondMailsCsv($listing),
                'pdf' => $this->respondMailsPdf($listing),
                default => JsonResponser::send(false, 'Mails retrieved.', $this->paginatedPayload($this->mailService->paginate($listing), MailResource::class)),
            };
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Admin\Communications\MailController@index');
        }
    }

    public function dashboard(MailDashboardRequest $request)
    {
        try {
            return JsonResponser::send(false, 'Mails dashboard retrieved.', $this->mailService->dashboard($request->validated()));
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Admin\Communications\MailController@dashboard');
        }
    }

    public function store(CreateMailRequest $request)
    {
        try {
            $admin = $this->requireAdmin($request);
            $mail = $this->mailService->create($request->validated(), $admin, $request);

            return JsonResponser::send(false, 'Mail drafted successfully.', MailResource::make($mail)->resolve(), 201);
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Admin\Communications\MailController@store');
        }
    }

    public function show(string $uuid)
    {
        try {
            $mail = $this->mailService->find($uuid);

            return JsonResponser::send(false, 'Mail retrieved.', MailResource::make($mail)->resolve());
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Admin\Communications\MailController@show');
        }
    }

    public function update(UpdateMailRequest $request, string $uuid)
    {
        try {
            $admin = $this->requireAdmin($request);
            $mail = $this->mailService->update($uuid, $request->validated(), $admin, $request);

            return JsonResponser::send(false, 'Mail updated successfully.', MailResource::make($mail)->resolve());
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Admin\Communications\MailController@update');
        }
    }

    public function destroy(Request $request, string $uuid)
    {
        try {
            $admin = $this->requireAdmin($request);
            $this->mailService->delete($uuid, $admin, $request);

            return JsonResponser::send(false, 'Mail deleted successfully.', null);
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Admin\Communications\MailController@destroy');
        }
    }

    public function send(SendMailRequest $request, string $uuid)
    {
        try {
            $admin = $this->requireAdmin($request);
            $mail = $this->mailService->send($uuid, $request->validated(), $admin, $request);

            return JsonResponser::send(false, 'Mail queued for delivery.', MailResource::make($mail)->resolve());
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Admin\Communications\MailController@send');
        }
    }

    public function resend(Request $request, string $uuid)
    {
        try {
            $admin = $this->requireAdmin($request);
            $mail = $this->mailService->resend($uuid, $admin, $request);

            return JsonResponser::send(false, 'Mail re-queued for outstanding recipients.', MailResource::make($mail)->resolve());
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Admin\Communications\MailController@resend');
        }
    }

    public function recipients(MailRecipientListRequest $request, string $uuid)
    {
        try {
            $listing = $request->validated();
            $export = $listing['export'] ?? null;

            return match ($export) {
                'csv' => $this->respondRecipientsCsv($uuid, $listing),
                'pdf' => $this->respondRecipientsPdf($uuid, $listing),
                default => JsonResponser::send(false, 'Recipients retrieved.', $this->paginatedPayload($this->mailService->paginateRecipients($uuid, $listing), MailRecipientResource::class)),
            };
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Admin\Communications\MailController@recipients');
        }
    }

    public function analytics(string $uuid)
    {
        try {
            return JsonResponser::send(false, 'Mail analytics retrieved.', $this->mailService->analytics($uuid));
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Admin\Communications\MailController@analytics');
        }
    }

    public function unsubscribers(CommunicationsListRequest $request)
    {
        try {
            $listing = $request->validated();
            $export = $listing['export'] ?? null;

            return match ($export) {
                'csv' => $this->respondUnsubscribersCsv($listing),
                'pdf' => $this->respondUnsubscribersPdf($listing),
                default => JsonResponser::send(false, 'Unsubscribers retrieved.', $this->paginatedPayload($this->mailService->paginateUnsubscribers($listing), EmailUnsubscribeResource::class)),
            };
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Admin\Communications\MailController@unsubscribers');
        }
    }

    /**
     * @param  array<string, mixed>  $listing
     */
    private function respondMailsCsv(array $listing): StreamedResponse
    {
        [$collection, $truncated] = $this->mailService->exportCollection($listing);
        $filename = 'mails-'.now()->format('Y-m-d-His').'.csv';

        return response()->streamDownload(function () use ($collection): void {
            $out = fopen('php://output', 'w');
            if ($out === false) {
                return;
            }

            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['Date', 'Subject', 'Sent By', 'Audience', 'Status']);

            foreach ($collection as $mail) {
                /** @var MailCampaign $mail */
                fputcsv($out, [
                    $mail->created_at?->toIso8601String() ?? '',
                    $mail->title,
                    $mail->sender?->displayName() ?? $mail->creator?->displayName() ?? '',
                    (int) ($mail->recipients_count ?? $mail->recipients()->count()),
                    $mail->status,
                ]);
            }

            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'X-Export-Truncated' => $truncated ? '1' : '0',
        ]);
    }

    /**
     * @param  array<string, mixed>  $listing
     */
    private function respondMailsPdf(array $listing)
    {
        [$collection, $truncated] = $this->mailService->exportCollection($listing);
        $filename = 'mails-'.now()->format('Y-m-d-His').'.pdf';
        $periodStart = ! empty($listing['start_date']) ? (string) $listing['start_date'] : 'All dates';
        $periodEnd = ! empty($listing['end_date']) ? (string) $listing['end_date'] : 'All dates';

        $headings = ['Date', 'Subject', 'Sent By', 'Audience', 'Status'];

        $rows = $collection->values()->map(fn (MailCampaign $mail): array => [
            $mail->created_at?->format('Y-m-d H:i') ?? '',
            $mail->title,
            $mail->sender?->displayName() ?? $mail->creator?->displayName() ?? '',
            (string) (int) ($mail->recipients_count ?? $mail->recipients()->count()),
            $mail->status,
        ]);

        return $this->pdfReportHelper->download(
            rows: $rows,
            headings: $headings,
            title: 'Mails',
            filename: $filename,
            orientation: 'portrait',
            periodStart: $periodStart,
            periodEnd: $periodEnd,
            generatedAt: now((string) config('app.timezone')),
            truncated: $truncated,
            includedRows: $rows->count(),
        );
    }

    /**
     * @param  array<string, mixed>  $listing
     */
    private function respondRecipientsCsv(string $uuid, array $listing): StreamedResponse
    {
        [$collection, $truncated] = $this->mailService->exportRecipientsCollection($uuid, $listing);
        $filename = 'mail-recipients-'.now()->format('Y-m-d-His').'.csv';

        return response()->streamDownload(function () use ($collection): void {
            $out = fopen('php://output', 'w');
            if ($out === false) {
                return;
            }

            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['Recipient', 'Email Address', 'Status', 'Sent At', 'Opened At']);

            foreach ($collection as $recipient) {
                /** @var MailRecipient $recipient */
                fputcsv($out, [
                    $recipient->user?->displayName() ?? '',
                    $recipient->email,
                    $recipient->status,
                    $recipient->sent_at?->toIso8601String() ?? '',
                    $recipient->opened_at?->toIso8601String() ?? '',
                ]);
            }

            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'X-Export-Truncated' => $truncated ? '1' : '0',
        ]);
    }

    /**
     * @param  array<string, mixed>  $listing
     */
    private function respondRecipientsPdf(string $uuid, array $listing)
    {
        [$collection, $truncated] = $this->mailService->exportRecipientsCollection($uuid, $listing);
        $filename = 'mail-recipients-'.now()->format('Y-m-d-His').'.pdf';

        $headings = ['Recipient', 'Email Address', 'Status', 'Sent At', 'Opened At'];

        $rows = $collection->values()->map(fn (MailRecipient $recipient): array => [
            $recipient->user?->displayName() ?? '',
            $recipient->email,
            $recipient->status,
            $recipient->sent_at?->format('Y-m-d H:i') ?? '',
            $recipient->opened_at?->format('Y-m-d H:i') ?? '',
        ]);

        return $this->pdfReportHelper->download(
            rows: $rows,
            headings: $headings,
            title: 'Mail Recipients',
            filename: $filename,
            orientation: 'portrait',
            generatedAt: now((string) config('app.timezone')),
            truncated: $truncated,
            includedRows: $rows->count(),
        );
    }

    /**
     * @param  array<string, mixed>  $listing
     */
    private function respondUnsubscribersCsv(array $listing): StreamedResponse
    {
        [$collection, $truncated] = $this->mailService->exportUnsubscribersCollection($listing);
        $filename = 'unsubscribers-'.now()->format('Y-m-d-His').'.csv';

        return response()->streamDownload(function () use ($collection): void {
            $out = fopen('php://output', 'w');
            if ($out === false) {
                return;
            }

            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['Date', 'Recipient', 'Email Address', 'Unsubscribed From']);

            foreach ($collection as $unsubscribe) {
                /** @var EmailUnsubscribe $unsubscribe */
                fputcsv($out, [
                    $unsubscribe->unsubscribed_at?->toIso8601String() ?? '',
                    $unsubscribe->user?->displayName() ?? '',
                    $unsubscribe->email,
                    $unsubscribe->mail?->title ?? '',
                ]);
            }

            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'X-Export-Truncated' => $truncated ? '1' : '0',
        ]);
    }

    /**
     * @param  array<string, mixed>  $listing
     */
    private function respondUnsubscribersPdf(array $listing)
    {
        [$collection, $truncated] = $this->mailService->exportUnsubscribersCollection($listing);
        $filename = 'unsubscribers-'.now()->format('Y-m-d-His').'.pdf';
        $periodStart = ! empty($listing['start_date']) ? (string) $listing['start_date'] : 'All dates';
        $periodEnd = ! empty($listing['end_date']) ? (string) $listing['end_date'] : 'All dates';

        $headings = ['Date', 'Recipient', 'Email Address', 'Unsubscribed From'];

        $rows = $collection->values()->map(fn (EmailUnsubscribe $unsubscribe): array => [
            $unsubscribe->unsubscribed_at?->format('Y-m-d H:i') ?? '',
            $unsubscribe->user?->displayName() ?? '',
            $unsubscribe->email,
            $unsubscribe->mail?->title ?? '',
        ]);

        return $this->pdfReportHelper->download(
            rows: $rows,
            headings: $headings,
            title: 'Unsubscribers',
            filename: $filename,
            orientation: 'portrait',
            periodStart: $periodStart,
            periodEnd: $periodEnd,
            generatedAt: now((string) config('app.timezone')),
            truncated: $truncated,
            includedRows: $rows->count(),
        );
    }

    /**
     * @param  class-string<JsonResource>  $resourceClass
     * @return array<string, mixed>
     */
    private function paginatedPayload(LengthAwarePaginator $paginator, string $resourceClass): array
    {
        $payload = $paginator->toArray();
        /** @var AnonymousResourceCollection $resource */
        $resource = $resourceClass::collection($paginator);
        $payload['data'] = $resource->resolve();

        return $payload;
    }

    private function requireAdmin(Request $request): Admin
    {
        $admin = $request->user();
        if (! $admin instanceof Admin) {
            abort(403, 'Forbidden.');
        }

        return $admin;
    }
}
