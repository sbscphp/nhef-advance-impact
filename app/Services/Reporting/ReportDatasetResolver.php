<?php

namespace App\Services\Reporting;

use App\Enums\ReportDatasetEnum;
use App\Exceptions\ApiException;
use App\Services\Reporting\Datasets\AdminUserReportDataset;
use App\Services\Reporting\Datasets\AlumniReportDataset;
use App\Services\Reporting\Datasets\CampaignReportDataset;
use App\Services\Reporting\Datasets\DonationReportDataset;
use App\Services\Reporting\Datasets\EventReportDataset;
use App\Services\Reporting\Datasets\EventWaitlistReportDataset;
use App\Services\Reporting\Datasets\MailReportDataset;
use App\Services\Reporting\Datasets\MentorshipReportDataset;
use App\Services\Reporting\Datasets\NetworkingReportDataset;
use App\Services\Reporting\Datasets\PledgeReportDataset;
use App\Services\Reporting\Datasets\ProspectReportDataset;
use App\Services\Reporting\Datasets\ReportDatasetInterface;

/**
 * Maps a dataset key to its {@see ReportDatasetInterface} implementation; the one place that
 * knows every concrete dataset, so adding one means a new class plus one line here.
 */
class ReportDatasetResolver
{
    public function __construct(
        private readonly AlumniReportDataset $alumniDataset,
        private readonly DonationReportDataset $donationDataset,
        private readonly CampaignReportDataset $campaignDataset,
        private readonly EventReportDataset $eventDataset,
        private readonly PledgeReportDataset $pledgeDataset,
        private readonly ProspectReportDataset $prospectDataset,
        private readonly MailReportDataset $mailDataset,
        private readonly MentorshipReportDataset $mentorshipDataset,
        private readonly NetworkingReportDataset $networkingDataset,
        private readonly AdminUserReportDataset $adminUserDataset,
        private readonly EventWaitlistReportDataset $eventWaitlistDataset,
    ) {}

    public function make(string $dataset): ReportDatasetInterface
    {
        return match (ReportDatasetEnum::tryFrom($dataset)) {
            ReportDatasetEnum::ALUMNI => $this->alumniDataset,
            ReportDatasetEnum::DONATION => $this->donationDataset,
            ReportDatasetEnum::CAMPAIGN => $this->campaignDataset,
            ReportDatasetEnum::EVENT => $this->eventDataset,
            ReportDatasetEnum::PLEDGE => $this->pledgeDataset,
            ReportDatasetEnum::PROSPECT => $this->prospectDataset,
            ReportDatasetEnum::MAIL => $this->mailDataset,
            ReportDatasetEnum::MENTORSHIP => $this->mentorshipDataset,
            ReportDatasetEnum::NETWORKING => $this->networkingDataset,
            ReportDatasetEnum::ADMIN_USER => $this->adminUserDataset,
            ReportDatasetEnum::EVENT_WAITLIST => $this->eventWaitlistDataset,
            null => throw new ApiException("Unsupported report dataset: {$dataset}", 422),
        };
    }
}
