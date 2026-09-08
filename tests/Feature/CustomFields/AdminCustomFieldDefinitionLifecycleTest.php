<?php

namespace Tests\Feature\CustomFields;

use App\Exceptions\ApiException;
use App\Models\Admin;
use App\Services\CustomFields\CustomFieldDefinitionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminCustomFieldDefinitionLifecycleTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_a_dropdown_field_with_parsed_options(): void
    {
        $admin = $this->makeAdmin();
        $service = app(CustomFieldDefinitionService::class);

        $definition = $service->create([
            'name' => 'Gender',
            'description' => 'Select gender',
            'type' => 'dropdown',
            'applicable_modules' => ['constituent_management', 'crm'],
            'options' => 'Male ; Female ; Prefer not to Say',
            'is_required' => true,
        ], $admin, Request::create('/'));

        $this->assertSame(['Male', 'Female', 'Prefer not to Say'], $definition->options);
        $this->assertSame(['constituent_management', 'crm'], $definition->applicable_modules);
        $this->assertTrue($definition->is_required);
        $this->assertSame('active', $definition->status);
        $this->assertDatabaseHas('audit_logs', ['action' => 'CUSTOM_FIELD_CREATED']);
    }

    public function test_admin_can_create_a_multi_select_field_with_parsed_options(): void
    {
        $admin = $this->makeAdmin();
        $service = app(CustomFieldDefinitionService::class);

        $definition = $service->create([
            'name' => 'Marital Status',
            'type' => 'multi_select',
            'applicable_modules' => ['constituent_management'],
            'options' => 'Married ; Divorce ; Windowed',
        ], $admin, Request::create('/'));

        $this->assertSame(['Married', 'Divorce', 'Windowed'], $definition->options);
    }

    public function test_creating_a_text_field_stores_no_options(): void
    {
        $admin = $this->makeAdmin();
        $service = app(CustomFieldDefinitionService::class);

        $definition = $service->create([
            'name' => 'Head of Department',
            'type' => 'text',
            'applicable_modules' => ['user_management'],
        ], $admin, Request::create('/'));

        $this->assertNull($definition->options);
    }

    public function test_admin_can_update_a_field(): void
    {
        $admin = $this->makeAdmin();
        $service = app(CustomFieldDefinitionService::class);
        $definition = $service->create([
            'name' => 'Head of Department',
            'type' => 'text',
            'applicable_modules' => ['user_management'],
        ], $admin, Request::create('/'));

        $updated = $service->update($definition->uuid, [
            'name' => 'Head of Unit',
            'is_searchable' => true,
        ], $admin, Request::create('/'));

        $this->assertSame('Head of Unit', $updated->name);
        $this->assertTrue($updated->is_searchable);
        $this->assertDatabaseHas('audit_logs', ['action' => 'CUSTOM_FIELD_UPDATED']);
    }

    public function test_list_can_filter_by_status_and_applicable_module(): void
    {
        $admin = $this->makeAdmin();
        $service = app(CustomFieldDefinitionService::class);

        $active = $service->create([
            'name' => 'Gender',
            'type' => 'dropdown',
            'applicable_modules' => ['crm'],
            'options' => 'Male ; Female',
        ], $admin, Request::create('/'));

        $toArchive = $service->create([
            'name' => 'Notes',
            'type' => 'text',
            'applicable_modules' => ['donation'],
        ], $admin, Request::create('/'));

        $service->archive($toArchive->uuid, $admin, Request::create('/'));

        $activeOnly = $service->paginateForAdmin(['filters' => ['status' => 'active']]);
        $this->assertSame(1, $activeOnly->total());
        $this->assertSame($active->uuid, $activeOnly->items()[0]->uuid);

        $crmOnly = $service->paginateForAdmin(['filters' => ['applicable_module' => 'crm']]);
        $this->assertSame(1, $crmOnly->total());
    }

    public function test_archiving_is_one_way(): void
    {
        $admin = $this->makeAdmin();
        $service = app(CustomFieldDefinitionService::class);
        $definition = $service->create([
            'name' => 'Notes',
            'type' => 'text',
            'applicable_modules' => ['donation'],
        ], $admin, Request::create('/'));

        $archived = $service->archive($definition->uuid, $admin, Request::create('/'));

        $this->assertSame('archived', $archived->status);
        $this->assertDatabaseHas('audit_logs', ['action' => 'CUSTOM_FIELD_ARCHIVED']);

        $this->expectException(ApiException::class);
        $service->archive($definition->uuid, $admin, Request::create('/'));
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
}
