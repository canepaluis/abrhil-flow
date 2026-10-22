<?php

namespace Tests\Feature;

use App\Models\Card;
use App\Models\Sprint;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SprintOverviewTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(CarbonImmutable::parse('2026-10-21 12:00:00', 'America/Cancun'));
    }

    private function sprint(string $start = '2026-10-19', string $end = '2026-10-30'): Sprint
    {
        return Sprint::create([
            'name' => 'Sprint', 'start_date' => $start, 'end_date' => $end,
            'members_count' => 1, 'daily_minutes' => 480, 'working_days' => [1, 2, 3, 4, 5],
        ]);
    }

    private function card(): Card
    {
        $team = DB::table('teams')->insertGetId(['name' => 'Equipo']);
        return Card::create([
            'title' => 'Trabajo', 'description' => 'Información de la tarea', 'team_id' => $team,
            'project_id' => DB::table('projects')->insertGetId(['name' => 'Proyecto', 'description' => 'Prueba', 'team_id' => $team]),
            'platform_id' => DB::table('platforms')->insertGetId(['name' => 'Web']),
            'card_type_id' => DB::table('card_types')->insertGetId(['name' => 'Task', 'description' => 'Tarea']),
            'status_id' => DB::table('statuses')->insertGetId(['name' => 'Desarrollo', 'order' => 3]),
        ]);
    }

    private function attach(Sprint $sprint, Card $card, bool $completed = false): void
    {
        $sprint->cards()->attach($card->id, [
            'start_date' => $sprint->start_date->toDateString(), 'end_date' => $sprint->end_date->toDateString(),
            'estimated_minutes' => 120, 'is_completed' => $completed,
        ]);
    }

    public function test_closure_requires_all_cards_approved_and_supports_progressive_early_release(): void
    {
        $sprint = $this->sprint();
        $url = "/api/sprints/$sprint->id/closure";
        $this->putJson($url, ['review_date' => '2026-10-19'])->assertUnprocessable();
        $card = $this->card();
        $this->attach($sprint, $card);
        $this->putJson($url, ['review_date' => '2026-10-19'])->assertUnprocessable();
        $card->update(['workflow_state' => 'testing_approved']);
        $this->putJson($url, ['review_date' => '2026-10-19'])->assertOk()
            ->assertJsonPath('data.closure.status', 'pending_release')->assertJsonPath('data.closure.review_date', '2026-10-19');
        $this->putJson($url, ['production_release_date' => '2026-10-21'])->assertUnprocessable()->assertJsonValidationErrors('production_release_date');
        $this->putJson($url, ['dev_review_date' => '2026-10-20', 'dev_review_jira_url' => 'https://example.atlassian.net/browse/AHD-100'])
            ->assertOk()->assertJsonPath('data.closure.review_date', '2026-10-19');
        $this->getJson('/api/sprints/current')->assertOk()->assertJsonPath('data.closure.status', 'pending_release');
        $this->putJson($url, ['production_release_date' => '2026-10-21'])->assertOk()
            ->assertJsonPath('data.closure.status', 'finalized')->assertJsonPath('data.completed_cards', 1);
        $this->getJson('/api/sprints/history')->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.closure.dev_review_jira_url', 'https://example.atlassian.net/browse/AHD-100')
            ->assertJsonPath('data.0.closure.production_release_date', '2026-10-21');
        $this->putJson($url, ['production_release_date' => null])->assertUnprocessable();
        $this->postJson("/api/sprints/$sprint->id/cards/$card->id/completion", ['is_completed' => false])->assertUnprocessable();
        $card->update(['workflow_state' => 'cancelled']);
        $this->getJson("/api/sprints/$sprint->id")->assertOk()->assertJsonPath('data.completed_cards', 1);
    }

    public function test_closure_validates_dates_jira_and_optional_link_and_stays_pending_after_end(): void
    {
        $sprint = $this->sprint('2026-10-13', '2026-10-17');
        $card = $this->card();
        $card->update(['workflow_state' => 'testing_approved']);
        $this->attach($sprint, $card);
        $url = "/api/sprints/$sprint->id/closure";
        $this->putJson($url, ['review_date' => '2026-10-22'])->assertUnprocessable();
        $this->putJson($url, ['review_date' => '2026-10-12'])->assertUnprocessable();
        $this->putJson($url, ['review_date' => '2026-10-20', 'dev_review_date' => '2026-10-19'])->assertUnprocessable();
        $this->putJson($url, ['dev_review_jira_url' => 'javascript:alert(1)'])->assertUnprocessable();
        $this->getJson('/api/sprints/current')->assertOk()->assertJsonPath('data.id', $sprint->id)->assertJsonPath('data.closure.status', 'pending_release')->assertJsonPath('data.completed_cards', 1);
        $this->putJson($url, ['review_date' => '2026-10-19', 'dev_review_date' => '2026-10-20', 'production_release_date' => '2026-10-21'])
            ->assertOk()->assertJsonPath('data.closure.status', 'finalized')->assertJsonPath('data.closure.dev_review_jira_url', null);
    }

    public function test_automatic_uses_last_created_only_when_today_is_inclusive_in_its_period(): void
    {
        $this->getJson('/api/sprints/current')->assertOk()->assertJsonPath('data', null)->assertJsonPath('mode', 'automatic');
        $first = $this->sprint('2026-10-21', '2026-10-21');
        $this->getJson('/api/sprints/current')->assertOk()->assertJsonPath('data.id', $first->id);
        $this->sprint('2026-11-02', '2026-11-13');
        $this->getJson('/api/sprints/current')->assertOk()->assertJsonPath('data', null);
    }

    public function test_manual_selection_persists_outside_dates_and_can_be_reset(): void
    {
        $past = $this->sprint('2026-10-05', '2026-10-16');
        $active = $this->sprint();
        $this->postJson('/api/sprints/current', ['sprint_id' => $past->id])->assertOk()->assertJsonPath('data.id', $past->id)->assertJsonPath('mode', 'manual');
        $this->sprint('2026-11-02', '2026-11-13');
        $this->getJson('/api/sprints/current')->assertOk()->assertJsonPath('data.id', $past->id);
        $this->postJson('/api/sprints/current', ['sprint_id' => null])->assertOk()->assertJsonPath('mode', 'automatic')->assertJsonPath('data', null);
        $this->postJson('/api/sprints/current', ['sprint_id' => $active->id])->assertOk()->assertJsonPath('data.id', $active->id);
        $this->postJson('/api/sprints/current', ['sprint_id' => 99999])->assertUnprocessable();
        $this->getJson('/api/sprints/current')->assertOk()->assertJsonPath('data.id', $active->id);
    }

    public function test_current_day_uses_cancun_instead_of_utc(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-20 03:00:00', 'UTC'));
        $sprint = $this->sprint('2026-10-19', '2026-10-19');
        $this->getJson('/api/sprints/current')->assertOk()->assertJsonPath('today', '2026-10-19')
            ->assertJsonPath('timezone', 'America/Cancun')->assertJsonPath('data.id', $sprint->id);
    }

    public function test_completion_is_scoped_to_sprint_and_preserved_after_card_state_changes(): void
    {
        $card = $this->card();
        $first = $this->sprint('2026-10-05', '2026-10-16');
        $second = $this->sprint();
        $this->attach($first, $card);
        $this->attach($second, $card);
        $this->postJson("/api/sprints/$first->id/cards/$card->id/completion", ['is_completed' => true])
            ->assertOk()->assertJsonPath('data.completed_cards', 1)->assertJsonPath('data.completed_minutes', 120)
            ->assertJsonPath('data.cards.0.is_completed', true)->assertJsonPath('data.cards.0.description', 'Información de la tarea');
        $this->getJson("/api/sprints/$second->id")->assertOk()->assertJsonPath('data.cards.0.is_completed', false);
        DB::table('statuses')->where('id', $card->status_id)->update(['name' => 'Liberado']);
        $card->delete();
        $this->getJson('/api/sprints/history')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.cards.0.is_completed', true);
        $this->postJson("/api/sprints/$first->id/cards/$card->id/completion", ['is_completed' => false])->assertOk()->assertJsonPath('data.completed_cards', 0);
    }

    public function test_completion_rejects_nonmembers_and_invalid_payloads(): void
    {
        $card = $this->card();
        $sprint = $this->sprint();
        $this->postJson("/api/sprints/$sprint->id/cards/$card->id/completion", ['is_completed' => true])->assertNotFound();
        $this->attach($sprint, $card);
        $this->postJson("/api/sprints/$sprint->id/cards/$card->id/completion", ['is_completed' => 'yes'])->assertUnprocessable();
        $this->getJson("/api/sprints/$sprint->id")->assertOk()->assertJsonPath('data.completed_cards', 0);
    }

    public function test_import_past_sprint_with_resolved_cards_and_edit_preserves_completion(): void
    {
        $card = $this->card();
        $plan = [
            'name' => 'Histórico', 'start_date' => '2026-10-05', 'end_date' => '2026-10-16',
            'members_count' => 1, 'daily_minutes' => 480, 'working_days' => [1, 2, 3, 4, 5],
            'cards' => [['id' => $card->id, 'start_date' => '2026-10-05', 'end_date' => '2026-10-07', 'estimated_minutes' => 180, 'is_completed' => true]],
            'participant_ids' => [User::factory()->create()->id],
        ];
        $id = $this->postJson('/api/sprints', $plan)->assertCreated()->assertJsonPath('data.completed_cards', 1)->json('data.id');
        $this->sprint();
        $this->sprint('2026-11-02', '2026-11-13');
        $this->getJson('/api/sprints/history')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $id);
        unset($plan['cards'][0]['is_completed']);
        $plan['cards'][0]['estimated_minutes'] = 240;
        $this->putJson("/api/sprints/$id", $plan)->assertOk()->assertJsonPath('data.completed_cards', 1)->assertJsonPath('data.completed_minutes', 240);
    }
}
