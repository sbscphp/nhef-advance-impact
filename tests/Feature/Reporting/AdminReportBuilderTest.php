<?php

namespace Tests\Feature\Reporting;

use App\Models\Admin;
use App\Models\Campaign;
use App\Models\Donation;
use App\Models\DonationPayment;
use App\Models\User;
use App\Services\CustomFields\CustomFieldDefinitionService;
use App\Services\CustomFields\CustomFieldValueService;
use App\Services\Reporting\ReportBuilderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminReportBuilderTest extends TestCase
{
    use RefreshDatabase;

    public function test_fields_for_dataset_includes_only_show_in_reports_custom_fields(): void
    {
        $admin = $this->makeAdmin();
        $definitionService = app(CustomFieldDefinitionService::class);
        $reportService = app(ReportBuilderService::class);

        $definitionService->create([
            'name' => 'Gender',
            'type' => 'dropdown',
            'applicable_modules' => ['constituent_management'],
            'options' => 'Male ; Female',
            'show_in_reports' => true,
        ], $admin, Request::create('/'));

        $definitionService->create([
            'name' => 'Internal Note',
            'type' => 'text',
            'applicable_modules' => ['constituent_management'],
            'show_in_reports' => false,
        ], $admin, Request::create('/'));

        $fields = $reportService->fieldsForDataset('alumni');

        $this->assertArrayHasKey('Custom Fields', $fields);
        $customLabels = array_column($fields['Custom Fields'], 'label');
        $this->assertContains('Gender', $customLabels);
        $this->assertNotContains('Internal Note', $customLabels);
    }

    public function test_preview_returns_live_rows_without_creating_a_history_row(): void
    {
        $reportService = app(ReportBuilderService::class);
        $this->makeUser();

        $paginator = $reportService->preview('alumni', ['full_name', 'email'], null, null, null, null, 15);

        $this->assertGreaterThan(0, $paginator->total());
        $this->assertDatabaseCount('generated_reports', 0);
    }

    public function test_generate_creates_a_history_row_with_merged_custom_field_value(): void
    {
        $admin = $this->makeAdmin();
        $user = $this->makeUser();
        $definitionService = app(CustomFieldDefinitionService::class);
        $valueService = app(CustomFieldValueService::class);
        $reportService = app(ReportBuilderService::class);

        $definition = $definitionService->create([
            'name' => 'Gender',
            'type' => 'dropdown',
            'applicable_modules' => ['constituent_management'],
            'options' => 'Male ; Female',
            'show_in_reports' => true,
        ], $admin, Request::create('/'));

        $valueService->updateForFieldable($user, 'constituent_management', [
            ['custom_field_uuid' => $definition->uuid, 'value' => 'Male'],
        ]);

        $result = $reportService->generate($admin, [
            'name' => 'My Report',
            'dataset' => 'alumni',
            'fields' => ['email', 'custom:'.$definition->uuid],
        ]);

        $this->assertDatabaseHas('generated_reports', ['name' => 'My Report', 'dataset' => 'alumni']);

        $row = $result['rows']->firstWhere('email', $user->email);
        $this->assertSame('Male', $row['custom:'.$definition->uuid]);
    }

    public function test_donation_custom_field_is_joined_by_donation_id_not_payment_id(): void
    {
        $admin = $this->makeAdmin();
        $user = $this->makeUser();
        $campaign = Campaign::create([
            'title' => 'Test Campaign', 'slug' => 'test-campaign-'.uniqid(),
            'currency' => 'NGN', 'goal_amount' => 100000, 'raised_amount' => 0,
            'allow_one_time' => true, 'allow_recurring' => true, 'allow_anonymous' => true,
            'status' => 'active',
        ]);
        $donation = Donation::create([
            'campaign_id' => $campaign->id, 'user_id' => $user->id, 'frequency' => 'one_time',
            'currency' => 'NGN', 'amount' => 5000, 'total_received' => 5000, 'status' => 'completed',
        ]);
        DonationPayment::create([
            'donation_id' => $donation->id, 'user_id' => $user->id, 'amount' => 5000, 'currency' => 'NGN',
            'gateway' => 'stripe', 'gateway_reference' => 'DON_TEST_'.uniqid(), 'status' => 'successful',
        ]);

        $definitionService = app(CustomFieldDefinitionService::class);
        $valueService = app(CustomFieldValueService::class);
        $reportService = app(ReportBuilderService::class);

        $definition = $definitionService->create([
            'name' => 'Employer Match',
            'type' => 'text',
            'applicable_modules' => ['donation'],
            'show_in_reports' => true,
        ], $admin, Request::create('/'));

        $valueService->updateForFieldable($donation, 'donation', [
            ['custom_field_uuid' => $definition->uuid, 'value' => 'Acme Corp'],
        ]);

        $paginator = $reportService->preview('donation', ['donor_name', 'custom:'.$definition->uuid], null, null, null, null, 15);
        $row = collect($paginator->items())->first();

        $this->assertSame('Acme Corp', $row['custom:'.$definition->uuid]);
    }

    public function test_reexport_of_a_saved_report_reflects_current_data(): void
    {
        $admin = $this->makeAdmin();
        $user = $this->makeUser();
        $reportService = app(ReportBuilderService::class);

        $result = $reportService->generate($admin, [
            'name' => 'Alumni Snapshot',
            'dataset' => 'alumni',
            'fields' => ['email', 'department'],
        ]);

        $user->update(['department' => 'Computer Science']);

        $export = $reportService->exportRows($result['report']);
        $row = $export['rows']->firstWhere('email', $user->email);

        $this->assertSame('Computer Science', $row['department']);
    }

    private function makeAdmin(): Admin
    {
        return Admin::create([
            'name' => 'Super Admin',
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => Hash::make('password'),
            'is_active' => true,
            'can_login' => true,
        ]);
    }

    private function makeUser(): User
    {
        return User::create([
            'firstname' => 'Test',
            'lastname' => 'User',
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => Hash::make('password'),
            'phone_number' => '+234'.fake()->numerify('##########'),
        ]);
    }
}
