<?php

namespace Tests\Feature;

use App\Models\Card;
use App\Models\Sprint;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class TimeOffTest extends TestCase
{
    use RefreshDatabase;

    private function plan(array $users, string $start = '2026-10-19', string $end = '2026-10-23'): array
    {
        return ['name' => 'Disponibilidad', 'start_date' => $start, 'end_date' => $end, 'participant_ids' => $users,
            'daily_minutes' => 480, 'working_days' => [1, 2, 3, 4, 5], 'cards' => []];
    }

    private function absence(User $user, string $start, string $end)
    {
        return $this->postJson("/api/users/$user->id/time-off", ['type' => 'vacation', 'start_date' => $start, 'end_date' => $end]);
    }

    private function assignedCard(?User $user, string $title): Card
    {
        $team = DB::table('teams')->insertGetId(['name' => 'Equipo']);
        return Card::create([
            'title' => $title, 'description' => 'Prueba', 'team_id' => $team, 'assigned_to' => $user?->id,
            'project_id' => DB::table('projects')->insertGetId(['name' => 'Proyecto', 'description' => 'Prueba', 'team_id' => $team]),
            'platform_id' => DB::table('platforms')->insertGetId(['name' => 'Web']),
            'card_type_id' => DB::table('card_types')->insertGetId(['name' => 'Task', 'description' => 'Tarea']),
            'status_id' => DB::table('statuses')->insertGetId(['name' => 'Desarrollo', 'order' => 3]),
        ]);
    }

    public function test_absences_clip_to_sprint_workdays_without_double_counting_or_affecting_nonparticipants(): void
    {
        [$first, $second, $outsider] = User::factory()->developer()->count(3)->create()->all();
        $id = $this->postJson('/api/sprints', $this->plan([$first->id, $second->id]))->assertCreated()
            ->assertJsonPath('data.capacity_minutes', 4800)->json('data.id');
        $this->absence($first, '2026-10-20', '2026-10-21')->assertCreated();
        $this->absence($first, '2026-10-21', '2026-10-22')->assertCreated();
        $this->absence($second, '2026-10-24', '2026-10-25')->assertCreated();
        $this->absence($outsider, '2026-10-01', '2026-12-31')->assertCreated();
        $this->getJson("/api/sprints/$id")->assertOk()->assertJsonPath('data.gross_capacity_minutes', 4800)
            ->assertJsonPath('data.absence_minutes', 1440)->assertJsonPath('data.capacity_minutes', 3360)
            ->assertJsonPath('data.participants.0.absent_dates', ['2026-10-20', '2026-10-21', '2026-10-22']);
    }

    public function test_absence_updates_cancel_and_restore_recalculate_every_affected_sprint(): void
    {
        $user = User::factory()->developer()->create();
        $first = $this->postJson('/api/sprints', $this->plan([$user->id]))->assertCreated()->json('data.id');
        $second = $this->postJson('/api/sprints', $this->plan([$user->id], '2026-10-26', '2026-10-30'))->assertCreated()->json('data.id');
        $absence = $this->absence($user, '2026-10-22', '2026-10-27')->assertCreated()
            ->assertJsonPath('data.is_cancelled', false)->json('data.id');
        $this->getJson("/api/sprints/$first")->assertJsonPath('data.capacity_minutes', 1440);
        $this->getJson("/api/sprints/$second")->assertJsonPath('data.capacity_minutes', 1440);
        $this->postJson("/api/users/$user->id/time-off/$absence/status", ['is_cancelled' => true])->assertOk();
        foreach ([$first, $second] as $id) {
            $this->getJson("/api/sprints/$id")->assertJsonPath('data.capacity_minutes', 2400);
        }
        $this->putJson("/api/users/$user->id/time-off/$absence", ['type' => 'day_off', 'start_date' => '2026-10-19', 'end_date' => '2026-10-19'])->assertOk();
        $this->getJson("/api/sprints/$first")->assertJsonPath('data.capacity_minutes', 2400);
        $this->postJson("/api/users/$user->id/time-off/$absence/status", ['is_cancelled' => false])->assertOk();
        $this->getJson("/api/sprints/$first")->assertJsonPath('data.capacity_minutes', 1920);
        $this->getJson("/api/sprints/$second")->assertJsonPath('data.capacity_minutes', 2400);
    }

    public function test_card_and_member_planning_warnings_follow_absences(): void
    {
        $user = User::factory()->developer()->create();
        $team = DB::table('teams')->insertGetId(['name' => 'Equipo']);
        $card = Card::create([
            'title' => 'Trabajo', 'description' => 'Prueba', 'team_id' => $team, 'assigned_to' => $user->id,
            'project_id' => DB::table('projects')->insertGetId(['name' => 'Proyecto', 'description' => 'Prueba', 'team_id' => $team]),
            'platform_id' => DB::table('platforms')->insertGetId(['name' => 'Web']),
            'card_type_id' => DB::table('card_types')->insertGetId(['name' => 'Task', 'description' => 'Tarea']),
            'status_id' => DB::table('statuses')->insertGetId(['name' => 'Desarrollo', 'order' => 3]),
        ]);
        $plan = $this->plan([$user->id]);
        $plan['cards'] = [['id' => $card->id, 'start_date' => '2026-10-19', 'end_date' => '2026-10-20', 'estimated_minutes' => 960]];
        $id = $this->postJson('/api/sprints', $plan)->assertCreated()->json('data.id');
        $this->absence($user, '2026-10-20', '2026-10-23')->assertCreated();
        $this->getJson("/api/sprints/$id")->assertOk()->assertJsonPath('data.capacity_minutes', 480)
            ->assertJsonPath('data.remaining_minutes', -480)->assertJsonCount(1, 'data.planning_warnings')
            ->assertJsonPath('data.cards.0.available_minutes', 480)
            ->assertJsonPath('data.cards.0.absence_dates', ['2026-10-20'])
            ->assertJsonCount(2, 'data.cards.0.planning_warnings');
        $this->assertDatabaseHas('sprint_cards', ['sprint_id' => $id, 'card_id' => $card->id, 'estimated_minutes' => 960]);
    }

    public function test_sprint_participants_are_required_distinct_valid_and_define_member_count(): void
    {
        $user = User::factory()->developer()->create();
        $plan = $this->plan([]);
        $this->postJson('/api/sprints', $plan)->assertUnprocessable()->assertJsonValidationErrors('participant_ids');
        $plan['participant_ids'] = [$user->id, $user->id, 999999];
        $this->postJson('/api/sprints', $plan)->assertUnprocessable()->assertJsonValidationErrors(['participant_ids.0', 'participant_ids.2']);
        $plan['participant_ids'] = [$user->id];
        $plan['members_count'] = 999;
        $this->postJson('/api/sprints', $plan)->assertCreated()->assertJsonPath('data.members_count', 1);
    }

    public function test_legacy_sprints_explicitly_require_participant_configuration(): void
    {
        $plan = $this->plan([]);
        unset($plan['participant_ids'], $plan['cards']);
        $sprint = Sprint::create($plan + ['members_count' => 2]);
        $this->getJson("/api/sprints/$sprint->id")->assertOk()->assertJsonPath('data.requires_participants', true)
            ->assertJsonPath('data.capacity_minutes', 0)->assertJsonCount(1, 'data.planning_warnings');
        $plan['participant_ids'] = [User::factory()->developer()->create()->id];
        $plan['cards'] = [];
        $this->putJson("/api/sprints/$sprint->id", $plan)->assertOk()->assertJsonPath('data.requires_participants', false)
            ->assertJsonPath('data.capacity_minutes', 2400);
    }

    public function test_absence_validation_and_user_scope(): void
    {
        [$first, $second] = User::factory()->developer()->count(2)->create()->all();
        $this->absence($first, '2026-10-21', '2026-10-20')->assertUnprocessable()->assertJsonValidationErrors('end_date');
        $this->postJson("/api/users/$first->id/time-off", ['type' => 'unknown', 'start_date' => 'invalid', 'end_date' => 'invalid'])->assertUnprocessable();
        $id = $this->absence($first, '2026-10-19', '2026-10-19')->assertCreated()->json('data.id');
        $this->putJson("/api/users/$second->id/time-off/$id", ['type' => 'day_off', 'start_date' => '2026-10-19', 'end_date' => '2026-10-19'])->assertNotFound();
        $this->postJson("/api/users/$second->id/time-off/$id/status", ['is_cancelled' => true])->assertNotFound();
        $this->postJson("/api/users/$first->id/time-off/$id/status", ['is_cancelled' => 'yes'])->assertUnprocessable();
        $this->getJson('/api/users')->assertOk()->assertJsonFragment(['id' => $id, 'type' => 'vacation', 'start_date' => '2026-10-19', 'end_date' => '2026-10-19', 'is_cancelled' => false]);
    }

    public function test_current_and_history_use_recalculated_capacity_with_zero_availability(): void
    {
        $this->travelTo(\Carbon\CarbonImmutable::parse('2026-10-21 12:00:00', 'America/Cancun'));
        $user = User::factory()->developer()->create();
        $this->postJson('/api/sprints', $this->plan([$user->id], '2026-10-12', '2026-10-16'))->assertCreated();
        $this->postJson('/api/sprints', $this->plan([$user->id]))->assertCreated();
        $this->absence($user, '2026-10-01', '2026-10-31')->assertCreated();
        $this->getJson('/api/sprints/current')->assertOk()->assertJsonPath('data.capacity_minutes', 0)->assertJsonPath('data.absence_minutes', 2400);
        $this->getJson('/api/sprints/history')->assertOk()->assertJsonPath('data.0.capacity_minutes', 0);
        $this->getJson('/api/sprints')->assertOk()->assertJsonPath('data.0.capacity_minutes', 0)->assertJsonPath('data.1.capacity_minutes', 0);
    }

    public function test_custom_weekdays_and_shorter_daily_hours_define_absence_deduction(): void
    {
        $user = User::factory()->developer()->create();
        $plan = $this->plan([$user->id], '2026-10-19', '2026-10-25');
        $plan['working_days'] = [6, 7];
        $plan['daily_minutes'] = 240;
        $id = $this->postJson('/api/sprints', $plan)->assertCreated()->json('data.id');
        $this->absence($user, '2026-10-23', '2026-10-24')->assertCreated();
        $this->getJson("/api/sprints/$id")->assertOk()->assertJsonPath('data.capacity_minutes', 240)->assertJsonPath('data.absence_minutes', 240);
    }

    public function test_only_developers_and_their_absences_contribute_even_with_multiple_roles(): void
    {
        [$lead, $developer] = User::factory()->developer()->count(2)->create()->all();
        $leaderRole = \App\Models\Role::firstOrCreate(['slug' => 'project-lead'], ['name' => 'Líder de proyecto']);
        $lead->roles()->attach($leaderRole);
        $testerRole = \App\Models\Role::firstOrCreate(['slug' => 'tester'], ['name' => 'Tester']);
        $lead->roles()->attach($testerRole); // Still counts once, because developer takes precedence.
        $tester = User::factory()->create();
        $tester->roles()->attach($testerRole);
        $unknown = User::factory()->create();
        $this->absence($lead, '2026-10-20', '2026-10-20')->assertCreated();
        $this->absence($tester, '2026-10-19', '2026-10-23')->assertCreated();
        $created = $this->postJson('/api/sprints', $this->plan([$lead->id, $developer->id, $tester->id, $unknown->id]))
            ->assertCreated()->assertJsonPath('data.members_count', 4)->assertJsonPath('data.developer_count', 2)
            ->assertJsonPath('data.gross_capacity_minutes', 4800)->assertJsonPath('data.absence_minutes', 480)
            ->assertJsonPath('data.capacity_minutes', 4320);
        $people = collect($created->json('data.participants'))->keyBy('id');
        $this->assertTrue($people[$lead->id]['counts_toward_capacity']);
        $this->assertFalse($people[$tester->id]['counts_toward_capacity']);
        $this->assertSame(0, $people[$tester->id]['available_minutes']);
        $this->assertFalse($people[$unknown->id]['counts_toward_capacity']);
    }

    public function test_tester_effort_does_not_consume_developer_capacity_and_unknown_effort_is_explicit(): void
    {
        $developer = User::factory()->developer()->create();
        $tester = User::factory()->create();
        $tester->roles()->attach(\App\Models\Role::firstOrCreate(['slug' => 'tester'], ['name' => 'Tester']));
        $devCard = $this->assignedCard($developer, 'Desarrollar');
        $testCard = $this->assignedCard($tester, 'Probar');
        $unknownCard = $this->assignedCard(null, 'Por asignar');
        $plan = $this->plan([$developer->id, $tester->id]);
        $plan['cards'] = array_map(fn ($item) => [
            'id' => $item[0]->id, 'start_date' => $plan['start_date'], 'end_date' => $plan['end_date'], 'estimated_minutes' => $item[1],
        ], [[$devCard, 180], [$testCard, 10000], [$unknownCard, 120]]);
        $response = $this->postJson('/api/sprints', $plan)->assertCreated()
            ->assertJsonPath('data.capacity_minutes', 2400)->assertJsonPath('data.estimated_minutes', 10300)
            ->assertJsonPath('data.development_estimated_minutes', 180)->assertJsonPath('data.other_estimated_minutes', 10000)
            ->assertJsonPath('data.unclassified_estimated_minutes', 120)->assertJsonPath('data.remaining_minutes', 2220);
        $cards = collect($response->json('data.cards'))->keyBy('id');
        $this->assertSame('development', $cards[$devCard->id]['capacity_scope']);
        $this->assertSame('other', $cards[$testCard->id]['capacity_scope']);
        $this->assertSame('unclassified', $cards[$unknownCard->id]['capacity_scope']);
    }

    public function test_sprint_without_developers_has_zero_development_capacity(): void
    {
        $tester = User::factory()->create();
        $tester->roles()->attach(\App\Models\Role::firstOrCreate(['slug' => 'tester'], ['name' => 'Tester']));
        $this->postJson('/api/sprints', $this->plan([$tester->id]))->assertCreated()
            ->assertJsonPath('data.capacity_minutes', 0)->assertJsonPath('data.developer_count', 0)
            ->assertJsonPath('data.requires_participants', false)->assertJsonCount(1, 'data.planning_warnings');
    }
}
