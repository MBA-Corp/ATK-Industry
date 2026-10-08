<?php

namespace Tests\Feature;

use App\Models\CompanyProfile;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CompanyProfileApiTest extends TestCase
{
    use RefreshDatabase;

    private function userWithUpdatePermission(): User
    {
        $user = User::factory()->create();

        $role = Role::create([
            'name' => 'Owner',
            'code' => 'owner',
        ]);

        $permission = Permission::create([
            'name' => 'Update Company Profile',
            'code' => 'company_profile.update',
        ]);

        $role->permissions()->attach(
            $permission->id
        );

        $user->roles()->attach(
            $role->id
        );

        return $user;
    }

    public function test_authenticated_user_gets_null_when_company_profile_does_not_exist(): void
    {
        $user = User::factory()->create();

        $this
            ->actingAs($user)
            ->getJson('/api/v1/company-profile')
            ->assertOk()
            ->assertJsonPath(
                'data',
                null
            );
    }

    public function test_authenticated_user_can_view_company_profile(): void
    {
        $user = User::factory()->create();

        CompanyProfile::create([
            'profile_key' => 'PRIMARY',
            'name' => 'Insan ATK',
            'address' => 'Bogor',
            'phone' => '08123456789',
        ]);

        $this
            ->actingAs($user)
            ->getJson('/api/v1/company-profile')
            ->assertOk()
            ->assertJsonPath(
                'data.name',
                'Insan ATK'
            )
            ->assertJsonPath(
                'data.address',
                'Bogor'
            )
            ->assertJsonPath(
                'data.phone',
                '08123456789'
            );
    }

    public function test_authorized_user_can_create_company_profile(): void
    {
        $user = $this->userWithUpdatePermission();

        $this
            ->actingAs($user)
            ->putJson('/api/v1/company-profile', [
                'name' => 'Insan ATK',
                'address' => 'Jl. Test No. 1',
                'phone' => '08123456789',
            ])
            ->assertCreated()
            ->assertJsonPath(
                'data.name',
                'Insan ATK'
            );

        $this->assertDatabaseHas(
            'company_profiles',
            [
                'profile_key' => 'PRIMARY',
                'name' => 'Insan ATK',
                'address' => 'Jl. Test No. 1',
                'phone' => '08123456789',
            ]
        );
    }

    public function test_authorized_user_updates_existing_profile_instead_of_creating_duplicate(): void
    {
        $user = $this->userWithUpdatePermission();

        CompanyProfile::create([
            'profile_key' => 'PRIMARY',
            'name' => 'Old Store',
            'address' => 'Old Address',
            'phone' => '0800000000',
        ]);

        $this
            ->actingAs($user)
            ->putJson('/api/v1/company-profile', [
                'name' => 'New Store',
                'address' => 'New Address',
                'phone' => '0811111111',
            ])
            ->assertOk()
            ->assertJsonPath(
                'data.name',
                'New Store'
            );

        $this->assertDatabaseCount(
            'company_profiles',
            1
        );

        $this->assertDatabaseHas(
            'company_profiles',
            [
                'profile_key' => 'PRIMARY',
                'name' => 'New Store',
            ]
        );
    }

    public function test_logo_can_be_uploaded(): void
    {
        Storage::fake('public');

        $user = $this->userWithUpdatePermission();

        $response = $this
            ->actingAs($user)
            ->put(
                '/api/v1/company-profile',
                [
                    'name' => 'Insan ATK',
                    'address' => 'Bogor',
                    'phone' => '08123456789',

                    'logo' => UploadedFile::fake()
                        ->image(
                            'logo.png',
                            300,
                            300
                        ),
                ],
                [
                    'Accept' => 'application/json',
                ]
            );

        $response->assertCreated();

        $profile = CompanyProfile::query()
            ->where(
                'profile_key',
                'PRIMARY'
            )
            ->firstOrFail();

        $this->assertNotNull(
            $profile->logo_path
        );

        Storage::disk('public')
            ->assertExists(
                $profile->logo_path
            );
    }

    public function test_replacing_logo_removes_old_logo(): void
    {
        Storage::fake('public');

        Storage::disk('public')->put(
            'company-profile/old.png',
            'old-logo'
        );

        $user = $this->userWithUpdatePermission();

        CompanyProfile::create([
            'profile_key' => 'PRIMARY',
            'name' => 'Insan ATK',
            'address' => 'Bogor',
            'phone' => '08123456789',
            'logo_path' => 'company-profile/old.png',
        ]);

        $this
            ->actingAs($user)
            ->put(
                '/api/v1/company-profile',
                [
                    'name' => 'Insan ATK',
                    'address' => 'Bogor',
                    'phone' => '08123456789',

                    'logo' => UploadedFile::fake()
                        ->image(
                            'new-logo.png',
                            300,
                            300
                        ),
                ],
                [
                    'Accept' => 'application/json',
                ]
            )
            ->assertOk();

        Storage::disk('public')
            ->assertMissing(
                'company-profile/old.png'
            );

        $profile = CompanyProfile::query()
            ->where(
                'profile_key',
                'PRIMARY'
            )
            ->firstOrFail();

        Storage::disk('public')
            ->assertExists(
                $profile->logo_path
            );
    }

    public function test_logo_can_be_removed(): void
    {
        Storage::fake('public');

        Storage::disk('public')->put(
            'company-profile/logo.png',
            'logo'
        );

        $user = $this->userWithUpdatePermission();

        CompanyProfile::create([
            'profile_key' => 'PRIMARY',
            'name' => 'Insan ATK',
            'address' => 'Bogor',
            'phone' => '08123456789',
            'logo_path' => 'company-profile/logo.png',
        ]);

        $this
            ->actingAs($user)
            ->putJson(
                '/api/v1/company-profile',
                [
                    'name' => 'Insan ATK',
                    'address' => 'Bogor',
                    'phone' => '08123456789',
                    'remove_logo' => true,
                ]
            )
            ->assertOk()
            ->assertJsonPath(
                'data.logo_path',
                null
            );

        Storage::disk('public')
            ->assertMissing(
                'company-profile/logo.png'
            );
    }

    public function test_user_without_permission_cannot_update_company_profile(): void
    {
        $user = User::factory()->create();

        $this
            ->actingAs($user)
            ->putJson('/api/v1/company-profile', [
                'name' => 'Forbidden Store',
                'address' => 'Forbidden Address',
                'phone' => '0800000000',
            ])
            ->assertForbidden();

        $this->assertDatabaseCount(
            'company_profiles',
            0
        );
    }

    public function test_guest_cannot_access_company_profile(): void
    {
        $this
            ->getJson('/api/v1/company-profile')
            ->assertUnauthorized();

        $this
            ->putJson('/api/v1/company-profile', [
                'name' => 'Guest Store',
                'address' => 'Guest Address',
                'phone' => '0800000000',
            ])
            ->assertUnauthorized();
    }

    public function test_multipart_upload_using_method_override(): void
    {
        Storage::fake('public');

        $user = $this->userWithUpdatePermission();

        $this
            ->actingAs($user)
            ->post(
                '/api/v1/company-profile',
                [
                    '_method' => 'PUT',
                    'name' => 'Insan ATK',
                    'address' => 'Bogor',
                    'phone' => '08123456789',
                    'logo' => UploadedFile::fake()
                        ->image('logo.png', 100, 100),
                ],
                [
                    'Accept' => 'application/json',
                ]
            )
            ->assertCreated()
            ->assertJsonPath(
                'data.name',
                'Insan ATK'
            );

        $profile = CompanyProfile::query()
            ->firstOrFail();

        $this->assertNotNull(
            $profile->logo_path
        );

        Storage::disk('public')
            ->assertExists(
                $profile->logo_path
            );
    }
}
