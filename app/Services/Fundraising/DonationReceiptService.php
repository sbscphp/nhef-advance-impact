<?php

namespace App\Services\Fundraising;

use App\Enums\PaymentMethodEnum;
use App\Exceptions\ApiException;
use App\Helpers\GeneralHelper;
use App\Models\DonationPayment;
use App\Repositories\Contracts\Donation\DonationPaymentRepositoryInterface;
use App\Repositories\Contracts\DonorTier\DonorTierRepositoryInterface;
use App\Services\Theme\ThemeResolver;
use App\Support\Money;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Support\Facades\File;
use Symfony\Component\HttpFoundation\Response;

/**
 * Renders the "Download Receipt" PDF for a single successful donation payment, using
 * resources/views/pdf/donation-receipt.blade.php and the same raw-Dompdf pattern as PDFReportHelper.
 */
class DonationReceiptService
{
    public function __construct(
        private readonly DonationPaymentRepositoryInterface $paymentRepository,
        private readonly DonorTierRepositoryInterface $donorTierRepository,
    ) {}

    public function render(DonationPayment $payment): Response
    {
        if ($payment->status !== 'successful') {
            throw new ApiException('A receipt is only available for a successful payment.', 422);
        }

        $donation = $payment->donation;
        $campaign = $donation->campaign;
        $theme = app(ThemeResolver::class)->resolveForMail();
        $logoPath = GeneralHelper::resolveMailLogoPath($theme);
        $logoDataUri = File::exists($logoPath)
            ? 'data:image/png;base64,'.base64_encode(File::get($logoPath))
            : null;

        $html = view('pdf.donation-receipt', [
            'badgeLabel' => 'Donation Receipt',
            'foundationName' => config('organization.foundation_name'),
            'taxId' => config('organization.tax_id'),
            'contactEmail' => config('organization.contact_email'),
            'website' => config('organization.website'),
            'logoDataUri' => $logoDataUri,
            'receiptNumber' => $payment->gateway_reference,
            'issuedAt' => now()->toFormattedDateString(),
            'amountFormatted' => Money::format($payment->amount, $payment->currency),
            'donorName' => $donation->donorName(),
            'donorEmail' => $donation->donorEmail(),
            'donationDate' => $payment->paid_at?->toFormattedDateString(),
            'paymentMethod' => $payment->method !== null ? PaymentMethodEnum::from($payment->method)->label() : null,
            'campaignName' => $campaign->title,
            'tierLabel' => $this->resolveTierLabel($payment),
            'taxStatement' => 'This receipt serves as an official record of your donation. Please retain it for tax purposes; consult your tax advisor regarding deductibility in your jurisdiction.',
            'thankYouMessage' => 'Thank you for your generous support. Your contribution helps advance our mission and makes a real difference.',
            'executiveDirectorName' => config('organization.executive_director_name'),
            'executiveDirectorTitle' => config('organization.executive_director_title'),
        ])->render();

        $options = new Options;
        $options->set('isPhpEnabled', true);
        $options->set('isRemoteEnabled', false);
        $options->set('defaultFont', 'sans-serif');

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        return new Response($dompdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="receipt-'.$payment->gateway_reference.'.pdf"',
        ]);
    }

    /**
     * Only meaningful for logged-in NGN donors; guests and non-NGN payments have no tier
     * standing on the (NGN-only) Recognition Wall, so the receipt simply omits it.
     */
    private function resolveTierLabel(DonationPayment $payment): ?string
    {
        if ($payment->user_id === null || $payment->currency !== 'NGN') {
            return null;
        }

        $total = $this->paymentRepository->sumSuccessfulForUser($payment->user_id, null, null);
        $tier = $this->donorTierRepository->findForAmount($total);

        return $tier?->name;
    }
}
