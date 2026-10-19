<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserTimeOff extends Model
{
    protected $fillable = ['type', 'start_date', 'end_date', 'is_cancelled'];

    protected function casts(): array
    {
        return ['start_date' => 'date:Y-m-d', 'end_date' => 'date:Y-m-d', 'is_cancelled' => 'boolean'];
    }
}
