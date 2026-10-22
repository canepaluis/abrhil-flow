<?php

namespace App\Models;

use Carbon\CarbonPeriod;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Sprint extends Model
{
    protected $fillable = ['name', 'goal', 'start_date', 'end_date', 'members_count', 'daily_minutes', 'working_days'];

    protected function casts(): array
    {
        return ['start_date' => 'date', 'end_date' => 'date', 'review_date' => 'date', 'dev_review_date' => 'date', 'production_release_date' => 'date', 'working_days' => 'array', 'members_count' => 'integer', 'daily_minutes' => 'integer'];
    }

    public function readyForClosure(): bool
    {
        return $this->cards->isNotEmpty() && $this->cards->every(fn ($card) => $card->workflow_state === 'testing_approved');
    }

    public function cards(): BelongsToMany
    {
        // Include archived cards to preserve the effort committed to an existing plan.
        return $this->belongsToMany(Card::class, 'sprint_cards')->withTrashed()
            ->withPivot(['start_date', 'end_date', 'estimated_minutes', 'is_completed'])->withTimestamps();
    }

    public function workingDates(): array
    {
        $dates = [];
        foreach (CarbonPeriod::create($this->start_date, $this->end_date) as $date) {
            if (in_array($date->dayOfWeekIso, $this->working_days, true)) {
                $dates[] = $date->toDateString();
            }
        }
        return $dates;
    }

    public function participants(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'sprint_user');
    }
}
