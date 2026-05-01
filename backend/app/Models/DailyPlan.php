<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DailyPlan extends Model
{
    protected $table = 'daily_plans';

    protected $casts = [
        'date' => 'date',
    ];

    protected $fillable = [
        'date',
        'activity_type_id',
        'assigned_to',
        'card_id',
        'estimated_minutes',
        'real_minutes'
    ];

    public function activityType()
    {
        return $this->belongsTo(ActivityType::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function card()
    {
        return $this->belongsTo(Card::class);
    }
}
