<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

use Illuminate\Database\Eloquent\SoftDeletes;

class Card extends Model
{
    use SoftDeletes;

    protected $table = 'cards';

    protected $fillable = [
        'title',
        'description',
        'jira_link',
        'priority',
        'card_type_id',
        'assigned_to',
        'module_id',
        'team_id',
        'platform_id',
        'project_id',
        'status_id',
        'creator_id'
    ];

    public function cardType()
    {
        return $this->belongsTo(CardType::class);
    }

    public function assignedTo()
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function module()
    {
        return $this->belongsTo(Module::class);
    }

    public function team()
    {
        return $this->belongsTo(Team::class);
    }

    public function platform()
    {
        return $this->belongsTo(Platform::class);
    }

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    public function status()
    {
        return $this->belongsTo(Status::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'creator_id');
    }
}