<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ActivityType extends Model
{
    protected $table = 'activity_types';

    protected $fillable = [
        'name',
        'description'
    ];

    public function dailyPlans()
    {
        return $this->hasMany(DailyPlan::class);
    }
}
