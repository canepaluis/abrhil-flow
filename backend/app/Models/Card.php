<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

use Illuminate\Database\Eloquent\SoftDeletes;

class Card extends Model
{
    use SoftDeletes;

    protected $table = 'cards';
    protected $with = ['documenter', 'developer.roles', 'tester', 'approvalOwner', 'workflowEvents'];

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
        'creator_id', 'workflow_state', 'requested_at', 'documenter_id', 'developer_id', 'tester_id',
        'approval_owner_id', 'correction_origin', 'correction_note', 'demo_key'
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

    public function sprints()
    {
        return $this->belongsToMany(Sprint::class, 'sprint_cards')
            ->withPivot(['start_date', 'end_date', 'estimated_minutes', 'is_completed'])->withTimestamps();
    }

    protected function casts(): array { return ['requested_at' => 'date']; }
    public function documenter() { return $this->belongsTo(User::class, 'documenter_id'); }
    public function developer() { return $this->belongsTo(User::class, 'developer_id'); }
    public function tester() { return $this->belongsTo(User::class, 'tester_id'); }
    public function approvalOwner() { return $this->belongsTo(User::class, 'approval_owner_id'); }
    public function workflowEvents() { return $this->hasMany(CardWorkflowEvent::class)->orderByDesc('id'); }

    public function workflowOwner(): ?User
    {
        return match (\App\Services\CardWorkflow::stage($this->workflow_state ?? 'documentation_pending')) {
            'documentation' => $this->documenter ?? $this->assignedTo,
            'approval' => $this->approvalOwner ?? $this->documenter ?? $this->assignedTo,
            'development' => $this->developer ?? $this->assignedTo,
            'testing', 'approved' => $this->tester ?? $this->assignedTo,
            default => $this->assignedTo,
        };
    }
}
