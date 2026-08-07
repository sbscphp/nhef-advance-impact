<?php

use App\Http\Controllers\v1\Admin\AuditTrail\AuditTrailController;
use App\Http\Controllers\v1\Admin\Auth\AdminLoginController;
use App\Http\Controllers\v1\Admin\Auth\PasswordController as AdminPasswordController;
use App\Http\Controllers\v1\Admin\Fundraising\BankController;
use App\Http\Controllers\v1\Admin\Fundraising\CampaignController as AdminCampaignController;
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

        Route::prefix('campaigns')->group(function () {
            Route::post('/', [AdminCampaignController::class, 'store'])
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
            Route::get('/{uuid}/donations', [AdminCampaignController::class, 'donations'])
                ->middleware(['permission:campaigns.read']);
            Route::get('/{uuid}/donations/overview', [AdminCampaignController::class, 'donationsOverview'])
                ->middleware(['permission:campaigns.read']);
            Route::get('/{uuid}/pledges', [AdminCampaignController::class, 'pledges'])
                ->middleware(['permission:campaigns.read']);
            Route::get('/{uuid}/donor-breakdown', [AdminCampaignController::class, 'donorBreakdown'])
                ->middleware(['permission:campaigns.read']);
        });
    });
});
