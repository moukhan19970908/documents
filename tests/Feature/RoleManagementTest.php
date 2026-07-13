<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoleManagementTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin', 'is_active' => true]);
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    public function test_admin_sees_the_roles_page_with_seeded_roles(): void
    {
        $this->actingAs($this->admin())
            ->get(route('admin.roles.index'))
            ->assertOk()
            ->assertSee('Роли и доступы')
            ->assertSee('Владелец процесса')
            ->assertSee('Линейный сотрудник');
    }

    public function test_admin_creates_a_role_and_assigns_several_users(): void
    {
        $users = User::factory()->count(2)->create(['role' => 'linear']);

        $this->actingAs($this->admin())->post(route('admin.roles.store'), [
            'code'  => 'auditor',
            'name'  => 'Аудитор',
            'icon'  => 'eye',
            'color' => 'amber',
            'level' => 3,
            'users' => $users->pluck('id')->all(),
        ])->assertRedirect(route('admin.roles.index'));

        $role = Role::where('code', 'auditor')->firstOrFail();
        $this->assertSame(2, $role->users()->count());
        $this->assertFalse($role->is_system);
    }

    public function test_a_user_holds_several_roles_at_once(): void
    {
        $user = User::factory()->create(['role' => 'linear']);
        $user->roles()->sync(Role::whereIn('code', ['registrar', 'observer'])->pluck('id'));

        $user = $user->fresh(['roles']);

        $this->assertEqualsCanonicalizing(['linear', 'registrar', 'observer'], $user->roleCodes());
        $this->assertTrue($user->hasRole('registrar'));
        $this->assertTrue($user->hasAnyRole(['observer', 'ceo']));
        $this->assertFalse($user->hasRole('admin'));
    }

    public function test_a_secondary_admin_role_grants_admin_access(): void
    {
        $user = User::factory()->create(['role' => 'linear']);

        // Primary role alone must not open the admin area.
        $this->actingAs($user)->get(route('admin.roles.index'))->assertForbidden();

        $user->roles()->attach(Role::where('code', 'admin')->value('id'));

        $this->actingAs($user->fresh())->get(route('admin.roles.index'))->assertOk();
        $this->assertTrue($user->fresh()->isAdmin());
    }

    public function test_system_role_cannot_be_deleted(): void
    {
        $role = Role::where('code', 'admin')->firstOrFail();

        $this->actingAs($this->admin())
            ->delete(route('admin.roles.destroy', $role))
            ->assertSessionHas('error');

        $this->assertModelExists($role);
    }

    public function test_role_in_use_as_primary_cannot_be_deleted(): void
    {
        $role = Role::where('code', 'registrar')->firstOrFail();
        User::factory()->create(['role' => 'registrar']);

        $this->actingAs($this->admin())
            ->delete(route('admin.roles.destroy', $role))
            ->assertSessionHas('error');

        $this->assertModelExists($role);
    }

    public function test_duplicating_a_role_copies_it_with_a_free_code(): void
    {
        $role = Role::where('code', 'observer')->firstOrFail();
        $role->users()->attach(User::factory()->create(['role' => 'linear'])->id);

        $this->actingAs($this->admin())
            ->post(route('admin.roles.duplicate', $role))
            ->assertRedirect();

        $copy = Role::where('code', 'observer_copy')->firstOrFail();
        $this->assertSame('Наблюдатель (копия)', $copy->name);
        $this->assertFalse($copy->is_system);
        $this->assertSame(1, $copy->users()->count());
    }

    public function test_user_form_saves_primary_and_additional_roles(): void
    {
        $user = User::factory()->create(['role' => 'linear']);
        $extra = Role::whereIn('code', ['registrar', 'head_unit'])->pluck('id')->all();

        $this->actingAs($this->admin())->put(route('admin.users.update', $user), [
            'name'      => $user->name,
            'email'     => $user->email,
            'role'      => 'head_unit',
            'roles'     => $extra,
            'is_active' => 1,
        ])->assertRedirect(route('admin.users.index'));

        $user = $user->fresh(['roles']);
        $this->assertSame('head_unit', $user->role);
        $this->assertEqualsCanonicalizing(['head_unit', 'registrar'], $user->roleCodes());
    }
}
