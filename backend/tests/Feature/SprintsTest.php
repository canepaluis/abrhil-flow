<?php

namespace Tests\Feature;

use App\Models\Card;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SprintsTest extends TestCase
{
    use RefreshDatabase;

    private function card(): Card
    {
        $team = DB::table('teams')->insertGetId(['name' => 'Equipo']);
        $project = DB::table('projects')->insertGetId(['name' => 'Proyecto', 'description' => 'Prueba', 'team_id' => $team]);
        return Card::create([
            'title' => 'Tarjeta para planear', 'description' => 'Prueba', 'team_id' => $team, 'project_id' => $project,
            'platform_id' => DB::table('platforms')->insertGetId(['name' => 'Web']),
            'card_type_id' => DB::table('card_types')->insertGetId(['name' => 'Task', 'description' => 'Tarea']),
            'status_id' => DB::table('statuses')->insertGetId(['name' => 'Desarrollo', 'order' => 1]),
        ]);
    }

    private function plan(): array
    {
        return [
            'name' => 'Sprint de prueba', 'goal' => 'Entregar el portal',
            'start_date' => '2026-10-19', 'end_date' => '2026-10-30',
            'members_count' => 2, 'daily_minutes' => 480, 'working_days' => [1, 2, 3, 4, 5],
            'cards' => [],
            'participant_ids' => \App\Models\User::factory()->developer()->count(2)->create()->modelKeys(),
        ];
    }

    public function test_create_and_read_plan_with_capacity_and_estimates(): void
    {
        $card = $this->card();
        $plan = $this->plan();
        $plan['cards'] = [['id' => $card->id, 'start_date' => '2026-10-19', 'end_date' => '2026-10-21', 'estimated_minutes' => 720]];
        $card->update(['assigned_to' => $plan['participant_ids'][0]]);
        $created = $this->postJson('/api/sprints', $plan)->assertCreated()
            ->assertJsonPath('data.capacity_minutes', 9600)
            ->assertJsonPath('data.estimated_minutes', 720)
            ->assertJsonPath('data.remaining_minutes', 8880)
            ->assertJsonCount(10, 'data.working_dates')
            ->assertJsonPath('data.cards.0.title', $card->title);
        $id = $created->json('data.id');
        $this->getJson("/api/sprints/$id")->assertOk()->assertJsonPath('data.cards.0.estimated_minutes', 720);
        $this->getJson('/api/sprints')->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_update_estimates_and_remove_cards_without_deleting_them(): void
    {
        $card = $this->card();
        $plan = $this->plan();
        $plan['cards'] = [['id' => $card->id, 'start_date' => $plan['start_date'], 'end_date' => $plan['end_date'], 'estimated_minutes' => 60]];
        $id = $this->postJson('/api/sprints', $plan)->assertCreated()->json('data.id');
        $plan['cards'][0]['estimated_minutes'] = 120;
        $this->putJson("/api/sprints/$id", $plan)->assertOk()->assertJsonPath('data.estimated_minutes', 120);
        $plan['cards'] = [];
        $this->putJson("/api/sprints/$id", $plan)->assertOk()->assertJsonCount(0, 'data.cards');
        $this->assertDatabaseCount('sprint_cards', 0);
        $this->assertDatabaseHas('cards', ['id' => $card->id]);
    }

    public function test_rejects_outside_reversed_duplicate_and_unknown_cards_without_writing(): void
    {
        $card = $this->card();
        $plan = $this->plan();
        $plan['cards'] = [
            ['id' => $card->id, 'start_date' => '2026-10-18', 'end_date' => '2026-10-17', 'estimated_minutes' => 0],
            ['id' => $card->id, 'start_date' => '2026-10-19', 'end_date' => '2026-10-31', 'estimated_minutes' => 1],
            ['id' => 99999, 'start_date' => '2026-10-19', 'end_date' => '2026-10-20', 'estimated_minutes' => 60],
        ];
        $this->postJson('/api/sprints', $plan)->assertUnprocessable()->assertJsonValidationErrors([
            'cards.0.id', 'cards.0.start_date', 'cards.0.end_date', 'cards.0.estimated_minutes', 'cards.1.end_date', 'cards.2.id',
        ]);
        $this->assertDatabaseCount('sprints', 0);
        $this->assertDatabaseCount('sprint_cards', 0);
    }

    public function test_custom_workdays_and_overload_are_supported(): void
    {
        $card = $this->card();
        $plan = $this->plan();
        $plan['working_days'] = [6];
        $card->update(['assigned_to' => $plan['participant_ids'][0]]);
        $plan['cards'] = [['id' => $card->id, 'start_date' => '2026-10-24', 'end_date' => '2026-10-24', 'estimated_minutes' => 1200]];
        $this->postJson('/api/sprints', $plan)->assertCreated()
            ->assertJsonPath('data.capacity_minutes', 960)->assertJsonPath('data.remaining_minutes', -240);
    }

    public function test_rejects_invalid_sprint_period_and_no_workdays(): void
    {
        $plan = $this->plan();
        $plan['end_date'] = '2026-10-18';
        $this->postJson('/api/sprints', $plan)->assertUnprocessable()->assertJsonValidationErrors(['end_date']);
        $plan['end_date'] = '2028-10-19';
        $this->postJson('/api/sprints', $plan)->assertUnprocessable()->assertJsonValidationErrors(['end_date']);
        $plan['start_date'] = '2026-10-24';
        $plan['end_date'] = '2026-10-25';
        $this->postJson('/api/sprints', $plan)->assertUnprocessable()->assertJsonValidationErrors(['working_days']);
    }

    public function test_card_period_requires_a_workday_and_failed_update_keeps_existing_plan(): void
    {
        $card = $this->card();
        $plan = $this->plan();
        $id = $this->postJson('/api/sprints', $plan)->assertCreated()->json('data.id');
        $plan['name'] = 'No guardar';
        $plan['cards'] = [['id' => $card->id, 'start_date' => '2026-10-24', 'end_date' => '2026-10-25', 'estimated_minutes' => 60]];
        $this->putJson("/api/sprints/$id", $plan)->assertUnprocessable()->assertJsonValidationErrors(['cards.0.end_date']);
        $this->assertDatabaseHas('sprints', ['id' => $id, 'name' => 'Sprint de prueba']);
    }

    public function test_same_card_can_keep_independent_estimates_in_two_sprints(): void
    {
        $card = $this->card();
        $plan = $this->plan();
        $plan['cards'] = [['id' => $card->id, 'start_date' => $plan['start_date'], 'end_date' => $plan['end_date'], 'estimated_minutes' => 60]];
        $first = $this->postJson('/api/sprints', $plan)->assertCreated()->json('data.id');
        $plan['start_date'] = '2026-11-02';
        $plan['end_date'] = '2026-11-13';
        $plan['cards'][0]['start_date'] = $plan['start_date'];
        $plan['cards'][0]['end_date'] = $plan['end_date'];
        $plan['cards'][0]['estimated_minutes'] = 180;
        $this->postJson('/api/sprints', $plan)->assertCreated()->assertJsonPath('data.estimated_minutes', 180);
        $this->getJson("/api/sprints/$first")->assertOk()->assertJsonPath('data.estimated_minutes', 60);
        $card->delete();
        $this->getJson("/api/sprints/$first")->assertOk()->assertJsonPath('data.cards.0.archived', true)->assertJsonPath('data.estimated_minutes', 60);
    }

    public function test_put_is_allowed_from_frontend(): void
    {
        $this->options('/api/sprints/1', [], ['Origin' => 'http://localhost:5173', 'Access-Control-Request-Method' => 'PUT'])
            ->assertSuccessful()->assertHeader('Access-Control-Allow-Origin', 'http://localhost:5173');
    }

    public function test_create_rejects_any_shared_day_including_containment_and_boundaries(): void
    {
        $plan = $this->plan();
        $plan['working_days'] = [1, 2, 3, 4, 5, 6, 7];
        $this->postJson('/api/sprints', $plan)->assertCreated();
        foreach ([
            ['2026-10-19', '2026-10-30'], // Identical period.
            ['2026-10-20', '2026-10-22'], // Entirely inside.
            ['2026-10-01', '2026-11-10'], // Entirely outside, enclosing the existing sprint.
            ['2026-10-15', '2026-10-22'], // Partial overlap on the left.
            ['2026-10-28', '2026-11-02'], // Partial overlap on the right.
            ['2026-10-10', '2026-10-19'], // Shares the starting day.
            ['2026-10-30', '2026-11-05'], // Shares the ending day.
        ] as [$start, $end]) {
            $plan['start_date'] = $start;
            $plan['end_date'] = $end;
            $this->postJson('/api/sprints', $plan)->assertUnprocessable()
                ->assertJsonValidationErrors(['start_date', 'end_date']);
        }
        $this->assertDatabaseCount('sprints', 1);
    }

    public function test_adjacent_periods_and_self_edit_are_allowed_but_conflicting_edit_is_atomic(): void
    {
        $plan = $this->plan();
        $plan['working_days'] = [1, 2, 3, 4, 5, 6, 7];
        $first = $this->postJson('/api/sprints', $plan)->assertCreated()->json('data.id');
        $plan['name'] = 'Nombre actualizado';
        $this->putJson("/api/sprints/$first", $plan)->assertOk();
        $plan['start_date'] = '2026-10-31';
        $plan['end_date'] = '2026-11-06';
        $second = $this->postJson('/api/sprints', $plan)->assertCreated()->json('data.id');
        $plan['start_date'] = '2026-10-05';
        $plan['end_date'] = '2026-10-18';
        $this->postJson('/api/sprints', $plan)->assertCreated();
        $plan['name'] = 'No guardar';
        $plan['start_date'] = '2026-10-30';
        $plan['end_date'] = '2026-11-06';
        $this->putJson("/api/sprints/$second", $plan)->assertUnprocessable()->assertJsonValidationErrors(['start_date', 'end_date']);
        $this->getJson("/api/sprints/$second")->assertOk()
            ->assertJsonPath('data.name', 'Nombre actualizado')->assertJsonPath('data.start_date', '2026-10-31');
        $this->assertDatabaseCount('sprints', 3);
    }
}
