<?php

namespace Tests\Feature;

use App\Domain\Clinical\Services\CatalogPublisher;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Service;
use App\Models\ServiceVersion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CatalogGovernanceTest extends TestCase
{
    use RefreshDatabase;

    private function reviewer(): User
    {
        $user = User::factory()->create();
        $role = Role::create([
            'code' => 'medical_reviewer',
            'name_ar' => 'مراجع طبي',
            'name_en' => 'Medical reviewer',
            'scope' => 'staff',
            'is_system' => true,
        ]);
        $permission = Permission::create([
            'code' => 'catalog.publish',
            'name_ar' => 'نشر الكتالوج',
            'name_en' => 'Publish catalog',
            'group' => 'catalog',
        ]);
        $role->permissions()->attach($permission->id);
        $user->roles()->attach($role->id);

        return $user;
    }

    public function test_non_reviewer_cannot_publish_a_service(): void
    {
        $user = User::factory()->create();
        $service = Service::factory()->draft()->create();

        Sanctum::actingAs($user);
        $this->postJson('/api/v1/admin/catalog/services/'.$service->public_id.'/publish')
            ->assertStatus(403);
        $this->assertSame('draft', $service->fresh()->status);
    }

    public function test_reviewer_publishes_and_creates_a_version_snapshot(): void
    {
        $reviewer = $this->reviewer();
        $service = Service::factory()->draft()->create();

        Sanctum::actingAs($reviewer);
        $response = $this->postJson('/api/v1/admin/catalog/services/'.$service->public_id.'/publish', [
            'change_note' => 'Initial clinical review',
        ])->assertOk();

        $this->assertSame('published', $response->json('service.status'));
        $this->assertSame(1, ServiceVersion::where('service_id', $service->id)->count());
        $this->assertSame('Initial clinical review', ServiceVersion::first()->change_note);
    }

    public function test_suspending_a_service_requires_a_reason_and_blocks_new_catalog_access(): void
    {
        $reviewer = $this->reviewer();
        $service = Service::factory()->create(['status' => 'published', 'published_at' => now()]);

        Sanctum::actingAs($reviewer);
        $this->postJson('/api/v1/admin/catalog/services/'.$service->public_id.'/suspend')
            ->assertStatus(422);

        $this->postJson('/api/v1/admin/catalog/services/'.$service->public_id.'/suspend', [
            'reason' => 'Clinical review pending',
        ])->assertOk();

        $this->assertSame('suspended', $service->fresh()->status);
        $this->getJson('/api/v1/services/'.$service->public_id)->assertStatus(404);
    }

    public function test_staff_middleware_rejects_customer_token_before_permission_check(): void
    {
        $customer = User::factory()->create();
        $service = Service::factory()->draft()->create();

        Sanctum::actingAs($customer);
        $this->postJson('/api/v1/admin/catalog/services/'.$service->public_id.'/publish')
            ->assertStatus(403)
            ->assertJsonPath('code', 'staff_only');
    }
}