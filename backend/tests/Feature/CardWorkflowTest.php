<?php

namespace Tests\Feature;

use App\Models\Card;
use App\Models\Role;
use App\Models\User;
use App\Services\CardWorkflow;
use Carbon\CarbonImmutable;
use Database\Seeders\TeamMemberSeeder;
use Database\Seeders\WorkflowDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CardWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private function card(): Card
    {
        $developer = User::factory()->developer()->create();
        $tester = User::factory()->create();
        $tester->roles()->attach(Role::firstOrCreate(['slug' => 'tester'], ['name' => 'Tester']));
        $team = DB::table('teams')->insertGetId(['name' => 'Equipo']);
        return Card::create([
            'title' => 'Tarjeta real de prueba', 'description' => 'Revisar flujo', 'assigned_to' => $developer->id,
            'documenter_id' => $developer->id, 'developer_id' => $developer->id, 'tester_id' => $tester->id,
            'approval_owner_id' => $developer->id, 'team_id' => $team,
            'project_id' => DB::table('projects')->insertGetId(['name' => 'Proyecto', 'description' => 'Prueba', 'team_id' => $team]),
            'platform_id' => DB::table('platforms')->insertGetId(['name' => 'Web']),
            'card_type_id' => DB::table('card_types')->insertGetId(['name' => 'Task', 'description' => 'Tarea']),
            'status_id' => DB::table('statuses')->insertGetId(['name' => 'Documentación', 'order' => 1]),
        ])->refresh();
    }

    private function advance(Card $card, array $states): void
    {
        foreach ($states as $state) {
            $this->putJson("/api/cards/$card->id/workflow", ['state' => $state])->assertOk()->assertJsonPath('data.workflow.state', $state);
        }
    }

    public function test_full_flow_exposes_queues_owners_and_history(): void
    {
        $card = $this->card();
        $states = ['documentation_in_progress', 'approval_pending', 'approval_in_review', 'development_ready', 'development_in_progress', 'testing_pending', 'testing_in_progress', 'testing_approved'];
        $this->advance($card, $states);
        $this->getJson("/api/cards/$card->id")->assertOk()->assertJsonPath('data.workflow.stage', 'approved')
            ->assertJsonPath('data.workflow.active_owner', $card->tester->name)->assertJsonCount(8, 'data.workflow.history');
    }

    public function test_direction_corrections_require_note_and_remain_in_history_on_resubmission(): void
    {
        $card = $this->card();
        $this->advance($card, ['documentation_in_progress', 'approval_pending', 'approval_in_review']);
        $this->putJson("/api/cards/$card->id/workflow", ['state' => 'documentation_corrections'])->assertUnprocessable()->assertJsonValidationErrors('note');
        $this->putJson("/api/cards/$card->id/workflow", ['state' => 'documentation_corrections', 'note' => 'Agregar criterio de aceptación.'])
            ->assertOk()->assertJsonPath('data.workflow.requires_corrections', true)->assertJsonPath('data.workflow.correction_origin', 'direction');
        $this->putJson("/api/cards/$card->id/workflow", ['state' => 'approval_pending'])->assertOk()
            ->assertJsonPath('data.workflow.requires_corrections', false)->assertJsonFragment(['note' => 'Agregar criterio de aceptación.']);
    }

    public function test_testing_corrections_preserve_development_effort_and_active_tester(): void
    {
        $card = $this->card();
        $this->advance($card, ['documentation_in_progress', 'approval_pending', 'approval_in_review', 'development_ready', 'development_in_progress', 'testing_pending', 'testing_in_progress']);
        // A handoff must never move the development estimate to the testing budget.
        $card->update(['assigned_to' => $card->tester_id]);
        $response = $this->postJson('/api/sprints', [
            'name' => 'Sprint', 'start_date' => '2026-10-19', 'end_date' => '2026-10-30',
            'participant_ids' => [$card->developer_id, $card->tester_id], 'daily_minutes' => 480, 'working_days' => [1, 2, 3, 4, 5],
            'cards' => [['id' => $card->id, 'start_date' => '2026-10-19', 'end_date' => '2026-10-30', 'estimated_minutes' => 480]],
        ])->assertCreated()->assertJsonPath('data.development_estimated_minutes', 480)->assertJsonPath('data.other_estimated_minutes', 0)
            ->assertJsonPath('data.cards.0.workflow.active_owner', $card->tester->name);
        $this->putJson("/api/cards/$card->id/workflow", ['state' => 'development_corrections', 'note' => 'Corregir filtro al exportar.'])
            ->assertOk()->assertJsonPath('data.workflow.correction_origin', 'testing')->assertJsonPath('data.workflow.stage', 'development');
        $this->advance($card, ['testing_pending']);
        $this->getJson('/api/sprints/'.$response->json('data.id'))->assertOk()->assertJsonPath('data.development_estimated_minutes', 480);
    }

    public function test_invalid_transitions_missing_owners_and_wrong_roles_do_not_write_history(): void
    {
        $card = $this->card();
        $this->putJson("/api/cards/$card->id/workflow", ['state' => 'testing_approved'])->assertUnprocessable();
        $this->putJson("/api/cards/$card->id/workflow", ['state' => 'documentation_in_progress', 'documenter_id' => null])->assertUnprocessable();
        $this->putJson("/api/cards/$card->id/workflow", ['developer_id' => $card->tester_id])->assertUnprocessable();
        $this->assertDatabaseCount('card_workflow_events', 0);
        $this->assertDatabaseHas('cards', ['id' => $card->id, 'workflow_state' => 'documentation_pending', 'documenter_id' => $card->documenter_id]);
    }

    public function test_age_uses_original_creation_date_and_rejects_future_dates(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-22 12:00:00', 'America/Cancun'));
        $card = $this->card();
        $this->putJson("/api/cards/$card->id/workflow", ['requested_at' => '2026-10-01'])->assertOk()->assertJsonPath('data.workflow.age_days', 21);
        $this->putJson("/api/cards/$card->id/workflow", ['requested_at' => '2026-10-23'])->assertUnprocessable();
        $this->putJson("/api/cards/$card->id/workflow", ['state' => 'cancelled', 'note' => 'Se descarta el alcance.'])->assertOk();
        $this->putJson("/api/cards/$card->id/workflow", ['state' => 'documentation_pending'])->assertUnprocessable();
    }

    public function test_demo_is_idempotent_identified_and_does_not_overwrite_real_cards(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-22 12:00:00', 'America/Cancun'));
        $real = $this->card();
        $this->seed(TeamMemberSeeder::class);
        $this->seed(WorkflowDemoSeeder::class);
        $this->seed(WorkflowDemoSeeder::class);
        $this->assertSame(14, Card::whereNotNull('demo_key')->count());
        $this->assertDatabaseCount('cards', 15);
        $this->assertDatabaseCount('sprints', 1);
        $this->assertDatabaseCount('sprint_cards', 14);
        $this->assertDatabaseHas('cards', ['id' => $real->id, 'title' => 'Tarjeta real de prueba', 'workflow_state' => 'documentation_pending']);
        $current = $this->getJson('/api/sprints/current')->assertOk()->assertJsonCount(14, 'data.cards')->json('data');
        $this->assertCount(2, array_filter($current['cards'], fn ($card) => $card['workflow']['requires_corrections']));
        $this->assertCount(14, array_filter($current['cards'], fn ($card) => $card['workflow']['is_demo']));
    }
}
