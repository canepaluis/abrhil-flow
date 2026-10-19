<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CardsTest extends TestCase
{
    use RefreshDatabase;

    private function catalogs(): array
    {
        $team = DB::table('teams')->insertGetId(['name' => 'Equipo de prueba']);
        $project = DB::table('projects')->insertGetId(['name' => 'Proyecto', 'description' => 'Prueba', 'team_id' => $team]);
        $platform = DB::table('platforms')->insertGetId(['name' => 'Web']);
        $type = DB::table('card_types')->insertGetId(['name' => 'Task', 'description' => 'Tarea']);
        $status = DB::table('statuses')->insertGetId(['name' => 'Desarrollo', 'order' => 1]);
        return ['team_id' => $team, 'project_id' => $project, 'platform_id' => $platform, 'card_type_id' => $type, 'status_id' => $status];
    }

    public function test_catalogs_creation_and_listing(): void
    {
        $ids = $this->catalogs();
        $this->getJson('/api/card-catalogs')->assertOk()
            ->assertJsonPath('data.projects.0.team_id', $ids['team_id'])
            ->assertJsonMissingPath('data.users.0.password');

        $response = $this->postJson('/api/cards', $ids + ['title' => 'Primera card', 'description' => 'Resultado esperado']);
        $response->assertCreated()->assertJsonPath('data.title', 'Primera card');
        $this->assertDatabaseHas('cards', ['title' => 'Primera card']);
        $this->getJson('/api/cards')->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.status.name', 'Desarrollo')
            ->assertJsonPath('data.0.assigned_to.name', null);
    }

    public function test_invalid_card_returns_field_errors(): void
    {
        $this->postJson('/api/cards', $this->catalogs() + ['title' => 'Card', 'priority' => -1])
            ->assertUnprocessable()->assertJsonValidationErrors(['description', 'priority']);
        $this->assertDatabaseCount('cards', 0);
    }

    public function test_frontend_origin_is_allowed(): void
    {
        $this->options('/api/cards', [], [
            'Origin' => 'http://localhost:5173',
            'Access-Control-Request-Method' => 'POST',
            'Access-Control-Request-Headers' => 'content-type',
        ])->assertSuccessful()->assertHeader('Access-Control-Allow-Origin', 'http://localhost:5173');
    }
}
