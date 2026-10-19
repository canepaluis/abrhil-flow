<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\TeamMemberSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class TeamMembersTest extends TestCase
{
    use RefreshDatabase;

    public function test_initial_team_has_multiple_roles_and_exact_responsibilities_without_credentials(): void
    {
        $this->seed(TeamMemberSeeder::class);
        $this->assertDatabaseCount('users', 4);
        $this->assertDatabaseCount('roles', 3);
        $this->assertDatabaseCount('role_user', 5);
        $luis = User::where('profile_key', 'luis-alexis-canepa')->firstOrFail();
        $this->assertEqualsCanonicalizing(['project-lead', 'developer'], $luis->roles->pluck('slug')->all());
        $this->assertSame(['Diseño', 'Documentación', 'Planeación', 'Desarrollo', 'Liberación', 'Presentación de avances a dirección'], $luis->responsibilities);
        $fernando = User::where('profile_key', 'fernando-kumul')->firstOrFail();
        $this->assertSame(['Diseño', 'Documentación', 'Desarrollo'], $fernando->responsibilities);
        foreach (['ana-morales', 'cristopher-itza'] as $key) {
            $user = User::where('profile_key', $key)->firstOrFail();
            $this->assertSame(['tester'], $user->roles->pluck('slug')->all());
            $this->assertSame(['Testing'], $user->responsibilities);
        }
        $this->assertSame(0, User::whereNotNull('password')->count());
        $this->assertSame(0, User::whereNotNull('email')->count());
    }

    public function test_reseeding_is_idempotent_and_preserves_existing_users_credentials_and_assignments(): void
    {
        $existing = User::factory()->create(['name' => 'Fernando Kumul']);
        $originalPassword = $existing->getRawOriginal('password');
        $originalEmail = $existing->email;
        $other = User::factory()->create(['name' => 'Otro usuario']);
        $this->seed(TeamMemberSeeder::class);
        $this->seed(TeamMemberSeeder::class);
        $this->assertDatabaseCount('users', 5);
        $this->assertDatabaseCount('roles', 3);
        $this->assertDatabaseCount('role_user', 5);
        $existing->refresh();
        $this->assertSame('fernando-kumul', $existing->profile_key);
        $this->assertSame($originalPassword, $existing->getRawOriginal('password'));
        $this->assertSame($originalEmail, $existing->email);
        $this->assertDatabaseHas('users', ['id' => $other->id, 'name' => 'Otro usuario']);
    }

    public function test_directory_exposes_roles_and_responsibilities_but_no_account_fields(): void
    {
        $this->seed(TeamMemberSeeder::class);
        $response = $this->getJson('/api/users')->assertOk()->assertJsonCount(4, 'data');
        foreach ($response->json('data') as $user) {
            $this->assertEqualsCanonicalizing(['id', 'name', 'roles', 'responsibilities', 'time_off'], array_keys($user));
        }
        $this->getJson('/api/card-catalogs')->assertOk()->assertJsonCount(4, 'data.users');
    }

    public function test_real_team_member_can_be_assigned_to_a_card(): void
    {
        $this->seed(TeamMemberSeeder::class);
        $user = User::where('profile_key', 'ana-morales')->firstOrFail();
        $team = DB::table('teams')->insertGetId(['name' => 'Equipo']);
        $this->postJson('/api/cards', [
            'title' => 'Revisar portal', 'description' => 'Probar la entrega', 'team_id' => $team, 'assigned_to' => $user->id,
            'project_id' => DB::table('projects')->insertGetId(['name' => 'Proyecto', 'description' => 'Prueba', 'team_id' => $team]),
            'platform_id' => DB::table('platforms')->insertGetId(['name' => 'Web']),
            'card_type_id' => DB::table('card_types')->insertGetId(['name' => 'Task', 'description' => 'Tarea']),
            'status_id' => DB::table('statuses')->insertGetId(['name' => 'Testing', 'order' => 4]),
        ])->assertCreated()->assertJsonPath('data.assigned_to.name', 'Ana Morales');
        $this->assertDatabaseHas('cards', ['assigned_to' => $user->id, 'title' => 'Revisar portal']);
    }
}
