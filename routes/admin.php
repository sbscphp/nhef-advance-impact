<?php

use App\Http\Controllers\v1\Admin\AuditTrail\AuditTrailController;
use App\Http\Controllers\v1\Admin\Auth\AdminLoginController;
use App\Http\Controllers\v1\Admin\Auth\PasswordController as AdminPasswordController;
use App\Http\Controllers\v1\Admin\Communications\CallLogController;
use App\Http\Controllers\v1\Admin\Communications\ConstituentPickerController;
use App\Http\Controllers\v1\Admin\Communications\MailController;
use App\Http\Controllers\v1\Admin\Communications\TaskController as CommunicationTaskController;
use App\Http\Controllers\v1\Admin\ConstituentManagement\ConstituentController;
use App\Http\Controllers\v1\Admin\Crm\ProposalCollaboratorController;
use App\Http\Controllers\v1\Admin\Crm\ProspectCallLogController;
use App\Http\Controllers\v1\Admin\Crm\ProspectController;
use App\Http\Controllers\v1\Admin\Crm\ProspectInviteController;
use App\Http\Controllers\v1\Admin\Crm\ProspectMessageController;
use App\Http\Controllers\v1\Admin\Crm\ProspectProposalController;
use App\Http\Controllers\v1\Admin\Events\EventController as AdminEventController;
use App\Http\Controllers\v1\Admin\Fundraising\BankController;
use App\Http\Controllers\v1\Admin\Fundraising\CampaignController as AdminCampaignController;
use App\Http\Controllers\v1\Admin\Fundraising\InstitutionController;
use App\Http\Controllers\v1\Admin\Mentorship\MatchingController as AdminMentorshipMatchingController;
use App\Http\Controllers\v1\Admin\Mentorship\MenteeController as AdminMentorshipMenteeController;
use App\Http\Controllers\v1\Admin\Mentorship\MentorController as AdminMentorshipMentorController;
use App\Http\Controllers\v1\Admin\Networking\AlumniSearchController;
use App\Http\Controllers\v1\Admin\Networking\ChannelController as AdminNetworkingChannelController;
use App\Http\Controllers\v1\Admin\Notification\NotificationController;
use App\Http\Controllers\v1\Admin\Settings\SettingsController;
use App\Http\Controllers\v1\Admin\UserManagement\UserManagementController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1/admin')->group(function () {
    Route::prefix('auth')->group(function () {
        Route::post('login', [AdminLoginController::class, 'login'])->middleware('throttle:admin-login');
        Route::post('login/verify-otp', [AdminLoginController::class, 'verifyOtp'])->middleware('throttle:admin-otp-verify');
        Route::post('login/resend-otp', [AdminLoginController::class, 'resendOtp'])->middleware('throttle:admin-otp-send');
        Route::post('forgot-password', [AdminPasswordController::class, 'forgotPassword'])->middleware('throttle:admin-otp-send');
        Route::post('forgot-password/link', [AdminPasswordController::class, 'forgotPasswordLink'])->middleware('throttle:admin-otp-send');
        Route::post('forgot-password/resend', [AdminPasswordController::class, 'forgotPasswordResend'])->middleware('throttle:admin-otp-send');
        Route::post('forgot-password/verify', [AdminPasswordController::class, 'forgotPasswordVerify'])->middleware('throttle:admin-otp-verify');
        Route::post('reset-password/context', [AdminPasswordController::class, 'resetPasswordContext'])->middleware('throttle:admin-reset-token-context');
        Route::post('reset-password', [AdminPasswordController::class, 'resetPassword'])->middleware('throttle:admin-otp-verify');
        Route::post('refresh', [AdminLoginController::class, 'refresh'])->middleware('throttle:admin-token-refresh');
        Route::middleware('auth:sanctum')->post('logout', [AdminLoginController::class, 'logout']);
    });

    Route::middleware(['auth:sanctum', 'permission:audit_trail.read'])->group(function () {
        Route::get('audit-trails', [AuditTrailController::class, 'index']);
    });

    Route::middleware('auth:sanctum')->prefix('notifications')->group(function () {
        Route::get('/', [NotificationController::class, 'index']);
        Route::post('/read-all', [NotificationController::class, 'markAllRead']);
        Route::get('/{id}', [NotificationController::class, 'show']);
        Route::patch('/{id}/read', [NotificationController::class, 'markRead']);
        Route::patch('/{id}/unread', [NotificationController::class, 'markUnread']);
        Route::delete('/{id}/dismiss', [NotificationController::class, 'dismiss']);
    });

    Route::middleware('auth:sanctum')->prefix('settings')->group(function () {
        Route::get('/profile', [SettingsController::class, 'profile']);
        Route::patch('/profile', [SettingsController::class, 'updateProfile']);
        Route::match(['patch', 'post'], '/2fa', [SettingsController::class, 'toggleTwoFactor']);
        Route::match(['patch', 'post'], '/password', [SettingsController::class, 'changePassword']);
        Route::match(['patch', 'post'], '/notifications', [SettingsController::class, 'updateNotificationPreferences']);
    });

    Route::middleware(['auth:sanctum'])->group(function () {
        Route::get('permissions', [UserManagementController::class, 'permissionList'])
            ->middleware(['permission:roles.read']);

        Route::prefix('roles')->group(function () {
            Route::get('/dropdown/{status?}', [UserManagementController::class, 'roleDropdown'])
                ->where('status', 'active|inactive|all')
                ->middleware(['permission:roles.read']);
            Route::get('/with-permissions', [UserManagementController::class, 'rolesWithPermissions'])
                ->middleware(['permission:roles.read']);
            Route::get('/stats', [UserManagementController::class, 'roleStats'])
                ->middleware(['permission:roles.read']);
            Route::post('/', [UserManagementController::class, 'createRole'])
                ->middleware(['permission:roles.create']);
            Route::get('/', [UserManagementController::class, 'roleList'])
                ->middleware(['permission:roles.read']);
            Route::get('/{roleId}', [UserManagementController::class, 'viewRole'])
                ->middleware(['permission:roles.read']);
            Route::patch('/{roleId}', [UserManagementController::class, 'updateRole'])
                ->middleware(['permission:roles.update']);
            Route::patch('/{roleId}/toggle-status', [UserManagementController::class, 'setRoleActiveStatus'])
                ->middleware(['permission:roles.update']);
            Route::delete('/{roleId}', [UserManagementController::class, 'deleteRole'])
                ->middleware(['permission:roles.delete']);
        });

        Route::prefix('admin-users')->group(function () {
            Route::get('/dropdown/{status?}', [UserManagementController::class, 'adminDropdown'])
                ->where('status', 'active|inactive|all')
                ->middleware(['permission:admins.read']);
            Route::get('/stats', [UserManagementController::class, 'adminStats'])
                ->middleware(['permission:admins.read']);
            Route::post('/', [UserManagementController::class, 'createAdmin'])
                ->middleware(['permission:admins.create']);
            Route::get('/', [UserManagementController::class, 'adminList'])
                ->middleware(['permission:admins.read']);
            Route::get('/{adminId}', [UserManagementController::class, 'viewAdmin'])
                ->middleware(['permission:admins.read']);
            Route::patch('/{adminId}', [UserManagementController::class, 'updateAdmin'])
                ->middleware(['permission:admins.update']);
            Route::patch('/{adminId}/toggle-status', [UserManagementController::class, 'setAdminActiveStatus'])
                ->middleware(['permission:admins.update']);
            Route::post('/{adminId}/resend-invite-link', [UserManagementController::class, 'resendAdminInviteLink'])
                ->middleware(['permission:admins.update']);
            Route::delete('/{adminId}', [UserManagementController::class, 'deleteAdmin'])
                ->middleware(['permission:admins.delete']);
        });

        Route::prefix('banks')->group(function () {
            Route::get('/dropdown', [BankController::class, 'dropdown'])
                ->middleware(['permission:campaigns.read']);
            Route::get('/accounts', [BankController::class, 'accountList'])
                ->middleware(['permission:campaigns.read']);
            Route::get('/resolve-account', [BankController::class, 'resolveAccount'])
                ->middleware(['permission:campaigns.create']);
            Route::post('/accounts', [BankController::class, 'createAccount'])
                ->middleware(['permission:campaigns.create']);
        });

        Route::prefix('institutions')->group(function () {
            Route::get('/', [InstitutionController::class, 'index'])
                ->middleware(['permission:campaigns.read']);
            Route::post('/', [InstitutionController::class, 'store'])
                ->middleware(['permission:campaigns.create']);
        });

        Route::prefix('campaigns')->group(function () {
            Route::post('/', [AdminCampaignController::class, 'store'])
                ->middleware(['permission:campaigns.create']);
            Route::post('/national-giving-day', [AdminCampaignController::class, 'storeNationalGivingDay'])
                ->middleware(['permission:campaigns.create']);
            Route::get('/', [AdminCampaignController::class, 'index'])
                ->middleware(['permission:campaigns.read']);
            Route::get('/{uuid}', [AdminCampaignController::class, 'show'])
                ->middleware(['permission:campaigns.read']);
            Route::patch('/{uuid}', [AdminCampaignController::class, 'update'])
                ->middleware(['permission:campaigns.update']);
            Route::patch('/{uuid}/pause', [AdminCampaignController::class, 'pause'])
                ->middleware(['permission:campaigns.update']);
            Route::patch('/{uuid}/resume', [AdminCampaignController::class, 'resume'])
                ->middleware(['permission:campaigns.update']);
            Route::get('/{uuid}/institutions', [AdminCampaignController::class, 'institutions'])
                ->middleware(['permission:campaigns.read']);
            Route::post('/{uuid}/institutions', [AdminCampaignController::class, 'addInstitution'])
                ->middleware(['permission:campaigns.create']);
            Route::put('/{uuid}/institutions', [AdminCampaignController::class, 'syncInstitutions'])
                ->middleware(['permission:campaigns.update']);
            Route::patch('/{uuid}/institutions/{institutionUuid}', [AdminCampaignController::class, 'updateInstitution'])
                ->middleware(['permission:campaigns.update']);
            Route::delete('/{uuid}/institutions/{institutionUuid}', [AdminCampaignController::class, 'removeInstitution'])
                ->middleware(['permission:campaigns.delete']);
            Route::get('/{uuid}/donations', [AdminCampaignController::class, 'donations'])
                ->middleware(['permission:campaigns.read']);
            Route::get('/{uuid}/donations/overview', [AdminCampaignController::class, 'donationsOverview'])
                ->middleware(['permission:campaigns.read']);
            Route::get('/{uuid}/pledges', [AdminCampaignController::class, 'pledges'])
                ->middleware(['permission:campaigns.read']);
            Route::get('/{uuid}/donor-breakdown', [AdminCampaignController::class, 'donorBreakdown'])
                ->middleware(['permission:campaigns.read']);
        });

        Route::prefix('events')->group(function () {
            Route::post('/', [AdminEventController::class, 'store'])
                ->middleware(['permission:events.create']);
            Route::get('/', [AdminEventController::class, 'index'])
                ->middleware(['permission:events.read']);
            // Must be registered before /{uuid}; otherwise "overview" would be swallowed as
            // a wildcard event uuid by the route below (same caution as networking/channels).
            Route::get('/overview', [AdminEventController::class, 'overview'])
                ->middleware(['permission:events.read']);
            Route::get('/{uuid}', [AdminEventController::class, 'show'])
                ->middleware(['permission:events.read']);
            Route::patch('/{uuid}', [AdminEventController::class, 'update'])
                ->middleware(['permission:events.update']);
            Route::patch('/{uuid}/deactivate', [AdminEventController::class, 'deactivate'])
                ->middleware(['permission:events.update']);
            Route::patch('/{uuid}/reactivate', [AdminEventController::class, 'reactivate'])
                ->middleware(['permission:events.update']);
            Route::patch('/{uuid}/archive', [AdminEventController::class, 'archive'])
                ->middleware(['permission:events.update']);
            Route::post('/{uuid}/reminder', [AdminEventController::class, 'sendReminder'])
                ->middleware(['permission:events.update']);
            Route::get('/{uuid}/report', [AdminEventController::class, 'downloadReport'])
                ->middleware(['permission:events.read']);
            Route::get('/{uuid}/analytics', [AdminEventController::class, 'analytics'])
                ->middleware(['permission:events.read']);
            Route::get('/{uuid}/ticket-sales', [AdminEventController::class, 'ticketSales'])
                ->middleware(['permission:events.read']);
            Route::get('/{uuid}/ticket-sales/{saleUuid}', [AdminEventController::class, 'ticketSale'])
                ->middleware(['permission:events.read']);
            Route::get('/{uuid}/waitlist', [AdminEventController::class, 'waitlist'])
                ->middleware(['permission:events.read']);
            Route::get('/{uuid}/waitlist/{entryUuid}', [AdminEventController::class, 'waitlistEntry'])
                ->middleware(['permission:events.read']);
        });

        Route::prefix('constituents/individuals')->group(function () {
            Route::post('/', [ConstituentController::class, 'store'])
                ->middleware(['permission:constituents.create']);
            Route::get('/', [ConstituentController::class, 'index'])
                ->middleware(['permission:constituents.read']);
            // Must be registered before /{uuid}; otherwise "overview" would be swallowed as
            // a wildcard constituent uuid by the route below (same caution as events/overview).
            Route::get('/overview', [ConstituentController::class, 'overview'])
                ->middleware(['permission:constituents.read']);
            Route::get('/{uuid}', [ConstituentController::class, 'show'])
                ->middleware(['permission:constituents.read']);
            Route::patch('/{uuid}', [ConstituentController::class, 'update'])
                ->middleware(['permission:constituents.update']);
            Route::patch('/{uuid}/revoke', [ConstituentController::class, 'revoke'])
                ->middleware(['permission:constituents.update']);
            Route::patch('/{uuid}/reactivate', [ConstituentController::class, 'reactivate'])
                ->middleware(['permission:constituents.update']);
            Route::post('/{uuid}/resend-invite', [ConstituentController::class, 'resendInvite'])
                ->middleware(['permission:constituents.update']);
            Route::get('/{uuid}/donations', [ConstituentController::class, 'donations'])
                ->middleware(['permission:constituents.read']);

            // Payment-level donations (Alumni Management's "Donations" tab); separate from
            // /{uuid}/donations above, which stays donation/subscription-level for its own consumers.
            Route::get('/{uuid}/payments/overview', [ConstituentController::class, 'paymentsOverview'])
                ->middleware(['permission:constituents.read']);
            Route::get('/{uuid}/payments', [ConstituentController::class, 'payments'])
                ->middleware(['permission:constituents.read']);
            Route::get('/{uuid}/payments/{paymentUuid}', [ConstituentController::class, 'showPayment'])
                ->middleware(['permission:constituents.read']);

            Route::get('/{uuid}/pledges/overview', [ConstituentController::class, 'pledgesOverview'])
                ->middleware(['permission:constituents.read']);
            Route::get('/{uuid}/pledges', [ConstituentController::class, 'pledges'])
                ->middleware(['permission:constituents.read']);
            Route::get('/{uuid}/pledges/{pledgeUuid}', [ConstituentController::class, 'showPledge'])
                ->middleware(['permission:constituents.read']);
            Route::post('/{uuid}/pledges/{pledgeUuid}/send-reminder', [ConstituentController::class, 'sendPledgeReminder'])
                ->middleware(['permission:constituents.update']);
        });

        Route::prefix('mentorship')->group(function () {
            Route::get('/mentors', [AdminMentorshipMentorController::class, 'index'])
                ->middleware(['permission:mentorship.read']);
            Route::get('/mentors/{uuid}', [AdminMentorshipMentorController::class, 'show'])
                ->middleware(['permission:mentorship.read']);
            Route::patch('/mentors/{uuid}/approve', [AdminMentorshipMentorController::class, 'approve'])
                ->middleware(['permission:mentorship.update']);
            Route::patch('/mentors/{uuid}/reject', [AdminMentorshipMentorController::class, 'reject'])
                ->middleware(['permission:mentorship.update']);
            Route::patch('/mentors/{uuid}/suspend', [AdminMentorshipMentorController::class, 'suspend'])
                ->middleware(['permission:mentorship.update']);
            Route::patch('/mentors/{uuid}/reactivate', [AdminMentorshipMentorController::class, 'reactivate'])
                ->middleware(['permission:mentorship.update']);
            Route::get('/mentors/{uuid}/reviews', [AdminMentorshipMentorController::class, 'reviews'])
                ->middleware(['permission:mentorship.read']);

            Route::get('/mentees', [AdminMentorshipMenteeController::class, 'index'])
                ->middleware(['permission:mentorship.read']);
            Route::get('/mentees/{uuid}', [AdminMentorshipMenteeController::class, 'show'])
                ->middleware(['permission:mentorship.read']);

            // The Matching Engine: unmatched mentees, ranked mentor recommendations, and manual matching.
            Route::get('/matching/unmatched-mentees', [AdminMentorshipMatchingController::class, 'unmatchedMentees'])
                ->middleware(['permission:mentorship.read']);
            Route::get('/matching/mentees/{uuid}/recommendations', [AdminMentorshipMatchingController::class, 'recommendations'])
                ->middleware(['permission:mentorship.read']);
            Route::post('/matching/matches', [AdminMentorshipMatchingController::class, 'store'])
                ->middleware(['permission:mentorship.update']);
            Route::get('/matching/matches/{uuid}/chat', [AdminMentorshipMatchingController::class, 'chat'])
                ->middleware(['permission:mentorship.read']);
        });

        Route::prefix('crm')->group(function () {
            Route::get('/prospects', [ProspectController::class, 'index'])
                ->middleware(['permission:crm.read']);
            Route::post('/prospects', [ProspectController::class, 'store'])
                ->middleware(['permission:crm.create']);
            // Must be registered before /prospects/{uuid}; otherwise "list" would be swallowed
            // as a wildcard prospect uuid by the route below (same caution as events/overview).
            Route::get('/prospects/list', [ProspectController::class, 'list'])
                ->middleware(['permission:crm.read']);
            Route::get('/prospects/{uuid}', [ProspectController::class, 'show'])
                ->middleware(['permission:crm.read']);
            Route::patch('/prospects/{uuid}', [ProspectController::class, 'update'])
                ->middleware(['permission:crm.update']);
            Route::patch('/prospects/{uuid}/stage', [ProspectController::class, 'changeStage'])
                ->middleware(['permission:crm.update']);

            Route::get('/prospects/{uuid}/calls', [ProspectCallLogController::class, 'index'])
                ->middleware(['permission:crm.read']);
            Route::post('/prospects/{uuid}/calls', [ProspectCallLogController::class, 'store'])
                ->middleware(['permission:crm.create']);
            Route::get('/prospects/{uuid}/calls/{callUuid}', [ProspectCallLogController::class, 'show'])
                ->middleware(['permission:crm.read']);

            Route::get('/prospects/{uuid}/invites', [ProspectInviteController::class, 'index'])
                ->middleware(['permission:crm.read']);
            Route::post('/prospects/{uuid}/invites', [ProspectInviteController::class, 'store'])
                ->middleware(['permission:crm.create']);
            Route::get('/prospects/{uuid}/invites/{inviteUuid}', [ProspectInviteController::class, 'show'])
                ->middleware(['permission:crm.read']);

            Route::get('/prospects/{uuid}/proposals', [ProspectProposalController::class, 'index'])
                ->middleware(['permission:crm.read']);
            Route::post('/prospects/{uuid}/proposals', [ProspectProposalController::class, 'store'])
                ->middleware(['permission:crm.create']);
            Route::get('/prospects/{uuid}/proposals/{proposalUuid}', [ProspectProposalController::class, 'show'])
                ->middleware(['permission:crm.read']);
            Route::patch('/prospects/{uuid}/proposals/{proposalUuid}', [ProspectProposalController::class, 'update'])
                ->middleware(['permission:crm.update']);
            Route::delete('/prospects/{uuid}/proposals/{proposalUuid}', [ProspectProposalController::class, 'destroy'])
                ->middleware(['permission:crm.delete']);
            Route::post('/prospects/{uuid}/proposals/{proposalUuid}/duplicate', [ProspectProposalController::class, 'duplicate'])
                ->middleware(['permission:crm.create']);
            Route::get('/prospects/{uuid}/proposals/{proposalUuid}/download/pdf', [ProspectProposalController::class, 'downloadPdf'])
                ->middleware(['permission:crm.read']);
            Route::get('/prospects/{uuid}/proposals/{proposalUuid}/download/word', [ProspectProposalController::class, 'downloadWord'])
                ->middleware(['permission:crm.read']);
            Route::post('/prospects/{uuid}/proposals/{proposalUuid}/send', [ProspectProposalController::class, 'sendToClient'])
                ->middleware(['permission:crm.update']);
            Route::post('/prospects/{uuid}/proposals/{proposalUuid}/resend', [ProspectProposalController::class, 'resend'])
                ->middleware(['permission:crm.update']);

            Route::get('/prospects/{uuid}/proposals/{proposalUuid}/collaborators', [ProposalCollaboratorController::class, 'index'])
                ->middleware(['permission:crm.read']);
            Route::post('/prospects/{uuid}/proposals/{proposalUuid}/collaborators', [ProposalCollaboratorController::class, 'store'])
                ->middleware(['permission:crm.create']);

            Route::get('/prospects/{uuid}/messages', [ProspectMessageController::class, 'index'])
                ->middleware(['permission:communications.read']);
            Route::post('/prospects/{uuid}/messages', [ProspectMessageController::class, 'store'])
                ->middleware(['permission:communications.create']);
            Route::get('/prospects/{uuid}/messages/{messageUuid}', [ProspectMessageController::class, 'show'])
                ->middleware(['permission:communications.read']);
        });

        Route::prefix('communications')->group(function () {
            // Must be registered before /mails/{uuid}; otherwise these would be swallowed as a
            // wildcard mail uuid by the route below (same caution as events/overview).
            Route::get('/mails/dashboard', [MailController::class, 'dashboard'])
                ->middleware(['permission:communications.read']);
            Route::get('/mails/unsubscribers', [MailController::class, 'unsubscribers'])
                ->middleware(['permission:communications.read']);

            Route::get('/mails', [MailController::class, 'index'])
                ->middleware(['permission:communications.read']);
            Route::post('/mails', [MailController::class, 'store'])
                ->middleware(['permission:communications.create']);
            Route::get('/mails/{uuid}', [MailController::class, 'show'])
                ->middleware(['permission:communications.read']);
            Route::patch('/mails/{uuid}', [MailController::class, 'update'])
                ->middleware(['permission:communications.update']);
            Route::delete('/mails/{uuid}', [MailController::class, 'destroy'])
                ->middleware(['permission:communications.delete']);
            Route::post('/mails/{uuid}/send', [MailController::class, 'send'])
                ->middleware(['permission:communications.update']);
            Route::post('/mails/{uuid}/resend', [MailController::class, 'resend'])
                ->middleware(['permission:communications.update']);
            Route::get('/mails/{uuid}/recipients', [MailController::class, 'recipients'])
                ->middleware(['permission:communications.read']);
            Route::get('/mails/{uuid}/analytics', [MailController::class, 'analytics'])
                ->middleware(['permission:communications.read']);

            Route::get('/constituents', [ConstituentPickerController::class, 'index'])
                ->middleware(['permission:communications.read']);
            Route::get('/assignable-admins', [CommunicationTaskController::class, 'assignableAdmins'])
                ->middleware(['permission:communications.read']);

            Route::get('/call-logs/overview', [CallLogController::class, 'overview'])
                ->middleware(['permission:communications.read']);
            Route::get('/call-logs', [CallLogController::class, 'index'])
                ->middleware(['permission:communications.read']);
            Route::post('/call-logs', [CallLogController::class, 'store'])
                ->middleware(['permission:communications.create']);
            Route::get('/call-logs/{uuid}', [CallLogController::class, 'show'])
                ->middleware(['permission:communications.read']);
            Route::post('/call-logs/{uuid}/tasks', [CallLogController::class, 'addTask'])
                ->middleware(['permission:communications.create']);

            Route::get('/tasks/overview', [CommunicationTaskController::class, 'overview'])
                ->middleware(['permission:communications.read']);
            Route::get('/tasks', [CommunicationTaskController::class, 'index'])
                ->middleware(['permission:communications.read']);
            Route::post('/tasks', [CommunicationTaskController::class, 'store'])
                ->middleware(['permission:communications.create']);
            Route::get('/tasks/{uuid}', [CommunicationTaskController::class, 'show'])
                ->middleware(['permission:communications.read']);
            Route::patch('/tasks/{uuid}', [CommunicationTaskController::class, 'update'])
                ->middleware(['permission:communications.update']);
            Route::delete('/tasks/{uuid}', [CommunicationTaskController::class, 'destroy'])
                ->middleware(['permission:communications.delete']);
            Route::patch('/tasks/{uuid}/mark-done', [CommunicationTaskController::class, 'markDone'])
                ->middleware(['permission:communications.update']);
            Route::patch('/tasks/{uuid}/recurrence/pause', [CommunicationTaskController::class, 'pauseRecurrence'])
                ->middleware(['permission:communications.update']);
            Route::patch('/tasks/{uuid}/recurrence/resume', [CommunicationTaskController::class, 'resumeRecurrence'])
                ->middleware(['permission:communications.update']);
            Route::patch('/tasks/{uuid}/recurrence/disable', [CommunicationTaskController::class, 'disableRecurrence'])
                ->middleware(['permission:communications.update']);
            Route::get('/tasks/{uuid}/instances', [CommunicationTaskController::class, 'instances'])
                ->middleware(['permission:communications.read']);
            Route::post('/tasks/{uuid}/notes', [CommunicationTaskController::class, 'addNote'])
                ->middleware(['permission:communications.create']);
        });

        Route::prefix('networking')->group(function () {
            Route::get('/alumni/search', [AlumniSearchController::class, 'index'])
                ->middleware(['permission:networking.read']);

            // Must be registered before /channels/{uuid}; otherwise "browse" would be swallowed
            // as a channel uuid by the wildcard route below.
            Route::get('/channels', [AdminNetworkingChannelController::class, 'index'])
                ->middleware(['permission:networking.read']);
            Route::post('/channels', [AdminNetworkingChannelController::class, 'store'])
                ->middleware(['permission:networking.create']);
            Route::get('/channels/{uuid}', [AdminNetworkingChannelController::class, 'show'])
                ->middleware(['permission:networking.read']);
            Route::patch('/channels/{uuid}', [AdminNetworkingChannelController::class, 'update'])
                ->middleware(['permission:networking.update']);
            Route::delete('/channels/{uuid}', [AdminNetworkingChannelController::class, 'destroy'])
                ->middleware(['permission:networking.delete']);
            Route::patch('/channels/{uuid}/lock', [AdminNetworkingChannelController::class, 'lock'])
                ->middleware(['permission:networking.update']);
            Route::patch('/channels/{uuid}/unlock', [AdminNetworkingChannelController::class, 'unlock'])
                ->middleware(['permission:networking.update']);

            Route::get('/channels/{uuid}/members', [AdminNetworkingChannelController::class, 'members'])
                ->middleware(['permission:networking.read']);
            Route::post('/channels/{uuid}/members', [AdminNetworkingChannelController::class, 'addMembers'])
                ->middleware(['permission:networking.update']);
            Route::delete('/channels/{uuid}/members/{memberUuid}', [AdminNetworkingChannelController::class, 'removeMember'])
                ->middleware(['permission:networking.update']);

            Route::get('/channels/{uuid}/messages', [AdminNetworkingChannelController::class, 'messages'])
                ->middleware(['permission:networking.read']);
        });
    });
});
