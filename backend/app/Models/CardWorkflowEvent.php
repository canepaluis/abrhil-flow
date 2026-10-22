<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CardWorkflowEvent extends Model
{
    public $timestamps = false;
    protected $fillable = ['from_state', 'to_state', 'note', 'created_at'];
    protected function casts(): array { return ['created_at' => 'datetime']; }
}
