<?php

namespace App\Services\Crm;

use App\Enums\AuditActionEnum;
use App\Enums\ModuleEnums;
use App\Enums\ProposalCollaboratorRoleEnum;
use App\Enums\ProposalStatusEnum;
use App\Enums\UserTypeEnum;
use App\Exceptions\ApiException;
use App\Helpers\FileUploadHelper;
use App\Helpers\GeneralHelper;
use App\Jobs\SendProposalToClientJob;
use App\Models\Admin;
use App\Models\ProposalCollaborator;
use App\Models\Prospect;
use App\Models\ProspectProposal;
use App\Notifications\GenericDatabaseNotification;
use App\Repositories\Contracts\Admin\AdminRepositoryInterface;
use App\Repositories\Contracts\Crm\ProposalCollaboratorRepositoryInterface;
use App\Repositories\Contracts\Crm\ProposalRecipientRepositoryInterface;
use App\Repositories\Contracts\Crm\ProspectProposalRepositoryInterface;
use App\Repositories\Contracts\Crm\ProspectRepositoryInterface;
use App\Services\Notifications\NotificationDispatchService;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\Shared\Html as PhpWordHtml;
use Symfony\Component\HttpFoundation\Response;

class ProposalService
{
    public function __construct(
        private readonly ProspectRepositoryInterface $prospectRepository,
        private readonly ProspectProposalRepositoryInterface $proposalRepository,
        private readonly ProposalCollaboratorRepositoryInterface $collaboratorRepository,
        private readonly ProposalRecipientRepositoryInterface $recipientRepository,
        private readonly AdminRepositoryInterface $adminRepository,
        private readonly NotificationDispatchService $notificationDispatchService,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public function create(string $prospectUuid, array $payload, Admin $actor, Request $request): ProspectProposal
    {
        $prospect = $this->findProspect($prospectUuid);

        $proposal = $this->proposalRepository->create([
            'prospect_id' => $prospect->id,
            'title' => $payload['title'],
            'body' => $payload['body'] ?? null,
            'created_by' => $actor->uuid,
            'status' => ProposalStatusEnum::DRAFT->value,
        ]);

        $this->collaboratorRepository->create([
            'proposal_id' => $proposal->id,
            'admin_id' => $actor->id,
            'role' => ProposalCollaboratorRoleEnum::OWNER->value,
            'invited_by' => $actor->uuid,
            'invited_at' => now(),
        ]);

        $proposal = $this->find($prospectUuid, $proposal->uuid);

        GeneralHelper::storeAuditLog(
            UserTypeEnum::ADMIN,
            AuditActionEnum::PROSPECT_PROPOSAL_CREATED,
            $request,
            $actor->uuid,
            ['prospect_uuid' => $prospect->uuid, 'proposal_uuid' => $proposal->uuid],
            $actor->displayName().' created a proposal for '.$prospect->fullName().': '.$proposal->title.'.',
            ProspectProposal::class,
            $proposal->uuid,
            ModuleEnums::crm,
            201,
        );

        return $proposal;
    }

    public function find(string $prospectUuid, string $proposalUuid): ProspectProposal
    {
        $prospect = $this->findProspect($prospectUuid);
        $proposal = $this->proposalRepository->findByUuidForProspect($prospect->id, $proposalUuid);

        if (! $proposal instanceof ProspectProposal) {
            throw new ApiException('Proposal not found.', 404);
        }

        return $proposal;
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function paginate(string $prospectUuid, array $filters): LengthAwarePaginator
    {
        $prospect = $this->findProspect($prospectUuid);
        $perPage = max(1, min((int) ($filters['per_page'] ?? 15), 100));

        return $this->proposalRepository->paginateForProspect($prospect->id, $filters, $perPage);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function update(string $prospectUuid, string $proposalUuid, array $payload, Admin $actor, Request $request): ProspectProposal
    {
        $proposal = $this->find($prospectUuid, $proposalUuid);
        $this->assertCanEdit($proposal, $actor);

        $data = array_filter([
            'title' => $payload['title'] ?? null,
            'body' => $payload['body'] ?? null,
        ], fn ($value) => $value !== null);

        $saveAs = $payload['save_as'] ?? 'save';

        if ($saveAs === ProposalStatusEnum::DRAFT->value) {
            $data['status'] = ProposalStatusEnum::DRAFT->value;
        } elseif ($proposal->status === ProposalStatusEnum::DRAFT->value) {
            // Only promotes a draft; never downgrades an already-sent proposal on edit.
            $data['status'] = ProposalStatusEnum::PENDING->value;
        }

        $proposal = $this->proposalRepository->update($proposal, $data);
        $proposal = $this->find($prospectUuid, $proposal->uuid);

        GeneralHelper::storeAuditLog(
            UserTypeEnum::ADMIN,
            AuditActionEnum::PROSPECT_PROPOSAL_UPDATED,
            $request,
            $actor->uuid,
            ['prospect_uuid' => $proposal->prospect_id, 'proposal_uuid' => $proposal->uuid, 'status' => $proposal->status],
            $actor->displayName().' updated proposal: '.$proposal->title.'.',
            ProspectProposal::class,
            $proposal->uuid,
            ModuleEnums::crm,
            200,
        );

        return $proposal;
    }

    public function duplicate(string $prospectUuid, string $proposalUuid, Admin $actor, Request $request): ProspectProposal
    {
        $original = $this->find($prospectUuid, $proposalUuid);
        $this->assertCanEdit($original, $actor);

        $copyPrefix = $original->title.' - Copy (';
        $copyNumber = $this->proposalRepository->countByTitlePrefix($original->prospect_id, $copyPrefix) + 1;
        $copyTitle = $copyPrefix.str_pad((string) $copyNumber, 2, '0', STR_PAD_LEFT).')';

        $duplicate = $this->proposalRepository->create([
            'prospect_id' => $original->prospect_id,
            'title' => $copyTitle,
            'body' => $original->body,
            'created_by' => $actor->uuid,
            'status' => ProposalStatusEnum::DRAFT->value,
        ]);

        $this->collaboratorRepository->create([
            'proposal_id' => $duplicate->id,
            'admin_id' => $actor->id,
            'role' => ProposalCollaboratorRoleEnum::OWNER->value,
            'invited_by' => $actor->uuid,
            'invited_at' => now(),
        ]);

        $duplicate = $this->find($prospectUuid, $duplicate->uuid);

        GeneralHelper::storeAuditLog(
            UserTypeEnum::ADMIN,
            AuditActionEnum::PROSPECT_PROPOSAL_DUPLICATED,
            $request,
            $actor->uuid,
            ['prospect_uuid' => $original->prospect_id, 'source_proposal_uuid' => $original->uuid, 'proposal_uuid' => $duplicate->uuid],
            $actor->displayName().' duplicated proposal: '.$original->title.'.',
            ProspectProposal::class,
            $duplicate->uuid,
            ModuleEnums::crm,
            201,
        );

        return $duplicate;
    }

    public function delete(string $prospectUuid, string $proposalUuid, Admin $actor, Request $request): void
    {
        $proposal = $this->find($prospectUuid, $proposalUuid);
        $this->assertIsOwner($proposal, $actor);

        GeneralHelper::storeAuditLog(
            UserTypeEnum::ADMIN,
            AuditActionEnum::PROSPECT_PROPOSAL_DELETED,
            $request,
            $actor->uuid,
            ['prospect_uuid' => $proposal->prospect_id, 'proposal_uuid' => $proposal->uuid],
            $actor->displayName().' deleted proposal: '.$proposal->title.'.',
            ProspectProposal::class,
            $proposal->uuid,
            ModuleEnums::crm,
            200,
        );

        $this->proposalRepository->delete($proposal);
    }

    /**
     * @return Collection<int, ProposalCollaborator>
     */
    public function listCollaborators(string $prospectUuid, string $proposalUuid): Collection
    {
        $proposal = $this->find($prospectUuid, $proposalUuid);

        return $this->collaboratorRepository->listForProposal($proposal->id);
    }

    /**
     * @param  array<int, array{admin_id: string, role: string}>  $collaborators
     * @return Collection<int, ProposalCollaborator>
     */
    public function inviteCollaborators(string $prospectUuid, string $proposalUuid, array $collaborators, Admin $actor, Request $request): Collection
    {
        $proposal = $this->find($prospectUuid, $proposalUuid);
        $this->assertIsOwner($proposal, $actor);

        $invited = collect();

        foreach ($collaborators as $entry) {
            $admin = $this->resolveAdmin($entry['admin_id']);

            if ($admin->id === $actor->id) {
                continue;
            }

            $existing = $this->collaboratorRepository->findForProposalAndAdmin($proposal->id, $admin->id);

            if ($existing instanceof ProposalCollaborator) {
                if ($existing->role === ProposalCollaboratorRoleEnum::OWNER->value) {
                    continue;
                }

                $collaborator = $this->collaboratorRepository->update($existing, [
                    'role' => $entry['role'],
                    'invited_by' => $actor->uuid,
                    'invited_at' => now(),
                ]);
            } else {
                $collaborator = $this->collaboratorRepository->create([
                    'proposal_id' => $proposal->id,
                    'admin_id' => $admin->id,
                    'role' => $entry['role'],
                    'invited_by' => $actor->uuid,
                    'invited_at' => now(),
                ]);
            }

            $collaborator->setRelation('admin', $admin);
            $invited->push($collaborator);

            $this->notificationDispatchService->notifyAdminsByUuids([$admin->uuid], new GenericDatabaseNotification(
                module: ModuleEnums::crm->value,
                event: 'proposal_collaborator_invited',
                title: 'You were added to a proposal',
                message: $actor->displayName().' gave you '.$entry['role'].' access to: '.$proposal->title.'.',
                meta: ['proposal_uuid' => $proposal->uuid],
            ));
        }

        if ($invited->isNotEmpty()) {
            GeneralHelper::storeAuditLog(
                UserTypeEnum::ADMIN,
                AuditActionEnum::PROSPECT_PROPOSAL_COLLABORATOR_INVITED,
                $request,
                $actor->uuid,
                ['proposal_uuid' => $proposal->uuid, 'admin_uuids' => $invited->pluck('admin.uuid')->all()],
                $actor->displayName().' invited '.$invited->count().' collaborator(s) to: '.$proposal->title.'.',
                ProspectProposal::class,
                $proposal->uuid,
                ModuleEnums::crm,
                201,
            );
        }

        return $invited;
    }

    public function downloadPdf(string $prospectUuid, string $proposalUuid): Response
    {
        $proposal = $this->find($prospectUuid, $proposalUuid);
        $prospect = $this->prospectRepository->findByUuid($prospectUuid);

        $html = $this->renderPdfHtml($proposal, $prospect);

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
            'Content-Disposition' => 'attachment; filename="'.$this->downloadFilename($proposal).'.pdf"',
        ]);
    }

    /** Used by {@see SendProposalToClientJob} to attach the same PDF to the outbound email. */
    public function buildPdfBytes(ProspectProposal $proposal): string
    {
        $html = $this->renderPdfHtml($proposal, $proposal->prospect);

        $options = new Options;
        $options->set('isPhpEnabled', true);
        $options->set('isRemoteEnabled', false);
        $options->set('defaultFont', 'sans-serif');

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        return $dompdf->output();
    }

    public function downloadWord(string $prospectUuid, string $proposalUuid): Response
    {
        $proposal = $this->find($prospectUuid, $proposalUuid);

        $phpWord = new PhpWord;
        $section = $phpWord->addSection();
        $section->addTitle($proposal->title, 1);
        PhpWordHtml::addHtml($section, $proposal->body ?? '', false, false);

        $tempPath = tempnam(sys_get_temp_dir(), 'proposal_').'.docx';
        $phpWord->save($tempPath, 'Word2007');
        $contents = File::get($tempPath);
        File::delete($tempPath);

        return new Response($contents, 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'Content-Disposition' => 'attachment; filename="'.$this->downloadFilename($proposal).'.docx"',
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function sendToClient(string $prospectUuid, string $proposalUuid, array $payload, Admin $actor, Request $request): ProspectProposal
    {
        $proposal = $this->find($prospectUuid, $proposalUuid);
        $this->assertCanEdit($proposal, $actor);
        $this->assertSendable($proposal);

        $prospect = $this->findProspect($prospectUuid);
        $attachmentUrls = FileUploadHelper::smartMultipleFileUpload($payload['attachments'] ?? null, 'crm/proposal-attachments');

        // The prospect always gets their own proposal; admin-entered emails are additional.
        $recipientEmails = array_values(array_unique(array_merge(
            [$prospect->email],
            $payload['recipient_emails'] ?? [],
        )));

        $proposal = $this->proposalRepository->update($proposal, [
            'send_message_title' => $payload['message_title'],
            'send_message_body' => $payload['message_body'],
            'attachments' => $attachmentUrls,
            'sent_by' => $actor->uuid,
            'status' => ProposalStatusEnum::PENDING->value,
        ]);

        // A fresh send replaces the recipient list wholesale, so every address starts `pending`.
        $this->recipientRepository->replaceForProposal($proposal->id, $recipientEmails);
        $proposal = $this->find($prospectUuid, $proposal->uuid);

        // Queued, not synchronous: status only updates once a worker actually runs the job.
        SendProposalToClientJob::dispatch($proposal->uuid);

        GeneralHelper::storeAuditLog(
            UserTypeEnum::ADMIN,
            AuditActionEnum::PROSPECT_PROPOSAL_SENT,
            $request,
            $actor->uuid,
            ['prospect_uuid' => $proposal->prospect_id, 'proposal_uuid' => $proposal->uuid, 'recipient_emails' => $recipientEmails],
            $actor->displayName().' sent proposal to client: '.$proposal->title.'.',
            ProspectProposal::class,
            $proposal->uuid,
            ModuleEnums::crm,
            200,
        );

        return $proposal;
    }

    /** Only retries recipients still `pending`/`failed`; anyone already `sent` is left alone. */
    public function resend(string $prospectUuid, string $proposalUuid, Admin $actor, Request $request): ProspectProposal
    {
        $proposal = $this->find($prospectUuid, $proposalUuid);
        $this->assertCanEdit($proposal, $actor);
        $this->assertSendable($proposal);

        $recipients = $this->recipientRepository->listForProposal($proposal->id);

        if ($recipients->isEmpty()) {
            throw new ApiException('This proposal has never been sent. Use Send to Client first.', 422);
        }

        $outstanding = $recipients->whereIn('status', ['pending', 'failed']);

        if ($outstanding->isEmpty()) {
            throw new ApiException('Every recipient has already received this proposal.', 422);
        }

        SendProposalToClientJob::dispatch($proposal->uuid);

        GeneralHelper::storeAuditLog(
            UserTypeEnum::ADMIN,
            AuditActionEnum::PROSPECT_PROPOSAL_RESENT,
            $request,
            $actor->uuid,
            ['prospect_uuid' => $proposal->prospect_id, 'proposal_uuid' => $proposal->uuid, 'outstanding_recipients' => $outstanding->pluck('email')->all()],
            $actor->displayName().' re-sent proposal to client: '.$proposal->title.'.',
            ProspectProposal::class,
            $proposal->uuid,
            ModuleEnums::crm,
            200,
        );

        return $proposal;
    }

    private function renderPdfHtml(ProspectProposal $proposal, ?Prospect $prospect): string
    {
        $logoPath = GeneralHelper::resolveMailLogoPath();
        $logoDataUri = File::exists($logoPath)
            ? 'data:image/png;base64,'.base64_encode(File::get($logoPath))
            : null;

        return view('pdf.proposal', [
            'title' => $proposal->title,
            'body' => $proposal->body ?? '',
            'prospectName' => $prospect?->fullName() ?? '',
            'generatedAt' => now()->toFormattedDateString(),
            'logoDataUri' => $logoDataUri,
            'foundationName' => config('organization.foundation_name'),
            'contactEmail' => config('organization.contact_email'),
            'website' => config('organization.website'),
        ])->render();
    }

    private function downloadFilename(ProspectProposal $proposal): string
    {
        return Str::slug($proposal->title).'-'.$proposal->uuid;
    }

    private function assertCanEdit(ProspectProposal $proposal, Admin $actor): void
    {
        $role = $proposal->collaboratorRole($actor);

        if ($role === null || ! $role->canEdit()) {
            throw new ApiException('You do not have edit access to this proposal.', 403);
        }
    }

    /** A draft isn't ready to send; a fully-sent proposal should be duplicated, not re-sent. */
    private function assertSendable(ProspectProposal $proposal): void
    {
        if ($proposal->status === ProposalStatusEnum::DRAFT->value) {
            throw new ApiException('This proposal is still a draft. Save it before sending it to a client.', 422);
        }

        if (in_array($proposal->status, [ProposalStatusEnum::SENT->value, ProposalStatusEnum::ACTIVE->value], true)) {
            throw new ApiException('This proposal has already been sent. Duplicate it to send a new copy.', 422);
        }
    }

    private function assertIsOwner(ProspectProposal $proposal, Admin $actor): void
    {
        if ($proposal->collaboratorRole($actor) !== ProposalCollaboratorRoleEnum::OWNER) {
            throw new ApiException('Only the proposal owner can do that.', 403);
        }
    }

    private function findProspect(string $uuid): Prospect
    {
        $prospect = $this->prospectRepository->findByUuid($uuid);

        if (! $prospect instanceof Prospect) {
            throw new ApiException('Prospect not found.', 404);
        }

        return $prospect;
    }

    private function resolveAdmin(string $uuid): Admin
    {
        $admin = $this->adminRepository->findByUuid($uuid);

        if (! $admin instanceof Admin) {
            throw new ApiException('The selected collaborator does not exist.', 422);
        }

        return $admin;
    }
}
