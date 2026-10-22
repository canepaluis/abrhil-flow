<?php

namespace App\Services;

use App\Models\Card;
use Illuminate\Validation\ValidationException;

class CardWorkflow
{
    public const LABELS = [
        'documentation_pending' => 'Por documentar', 'documentation_in_progress' => 'En documentación',
        'documentation_corrections' => 'Corregir documentación', 'approval_pending' => 'Por enviar a dirección',
        'approval_in_review' => 'En aprobación por dirección', 'development_ready' => 'Disponible para desarrollo',
        'development_in_progress' => 'En desarrollo', 'development_corrections' => 'Corregir desarrollo',
        'testing_pending' => 'Pendiente de testing', 'testing_in_progress' => 'En testing',
        'testing_approved' => 'Aprobada por testing', 'cancelled' => 'Cancelada',
    ];
    public const TRANSITIONS = [
        'documentation_pending' => ['documentation_in_progress'],
        'documentation_in_progress' => ['approval_pending'],
        'documentation_corrections' => ['approval_pending'],
        'approval_pending' => ['approval_in_review'],
        'approval_in_review' => ['development_ready', 'documentation_corrections'],
        'development_ready' => ['development_in_progress'],
        'development_in_progress' => ['testing_pending'],
        'development_corrections' => ['testing_pending'],
        'testing_pending' => ['testing_in_progress'],
        'testing_in_progress' => ['testing_approved', 'development_corrections'],
        'testing_approved' => [], 'cancelled' => [],
    ];
    public const ACTIONS = [
        'documentation_in_progress' => 'Iniciar documentación', 'approval_pending' => 'Marcar documentación lista para enviar',
        'approval_in_review' => 'Registrar envío a aprobación por dirección', 'development_ready' => 'Registrar aprobación de dirección',
        'development_in_progress' => 'Iniciar desarrollo', 'testing_pending' => 'Enviar a la cola de testing',
        'testing_in_progress' => 'Iniciar testing', 'testing_approved' => 'Registrar aprobación de testing',
        'documentation_corrections' => 'Solicitar correcciones de documentación',
        'development_corrections' => 'Solicitar correcciones de desarrollo', 'cancelled' => 'Cancelar tarjeta',
    ];

    public static function stage(string $state): string
    {
        if ($state === 'testing_approved') return 'approved';
        if ($state === 'cancelled') return 'cancelled';
        return explode('_', $state)[0];
    }

    public function move(Card $card, string $state, ?string $note): void
    {
        $from = $card->workflow_state;
        if ($from === $state) return;
        $allowed = self::TRANSITIONS[$from] ?? [];
        if ($from !== 'cancelled') $allowed[] = 'cancelled';
        if (!in_array($state, $allowed, true)) {
            throw ValidationException::withMessages(['state' => 'La transición no está permitida desde la etapa actual. Actualiza la tarjeta y revisa las acciones disponibles.']);
        }
        if (in_array($state, ['documentation_corrections', 'development_corrections', 'cancelled'], true) && !trim($note ?? '')) {
            throw ValidationException::withMessages(['note' => 'Indica qué debe corregirse o el motivo de cancelación.']);
        }
        $requiredOwner = match ($state) {
            'documentation_in_progress' => 'documenter_id', 'development_in_progress' => 'developer_id',
            'testing_in_progress' => 'tester_id', default => null,
        };
        if ($requiredOwner && !$card->$requiredOwner) {
            throw ValidationException::withMessages([$requiredOwner => 'Asigna un responsable para iniciar esta etapa.']);
        }
        $card->workflow_state = $state;
        $statusName = match (self::stage($state)) {
            'documentation' => 'Documentación', 'approval' => 'Aprobación', 'development' => 'Desarrollo',
            'testing' => 'Testing', 'approved' => 'Liberable', 'cancelled' => 'Cancelado',
        };
        $statusId = \App\Models\Status::where('name', $statusName)->value('id');
        if ($statusId) $card->status_id = $statusId;
        $card->correction_origin = match ($state) {
            'documentation_corrections' => 'direction', 'development_corrections' => 'testing', default => null,
        };
        $card->correction_note = $card->correction_origin ? trim($note) : null;
        $card->workflowEvents()->create(['from_state' => $from, 'to_state' => $state, 'note' => $note, 'created_at' => now()]);
    }

    public function describe(Card $card): array
    {
        $state = $card->workflow_state ?? 'documentation_pending';
        $created = $card->requested_at ?? $card->created_at;
        $today = now(config('sprints.timezone'))->startOfDay();
        $actions = self::TRANSITIONS[$state] ?? [];
        if ($state !== 'cancelled') $actions[] = 'cancelled';
        return [
            'state' => $state, 'label' => self::LABELS[$state], 'stage' => self::stage($state),
            'requires_corrections' => $card->correction_origin !== null,
            'correction_origin' => $card->correction_origin, 'correction_note' => $card->correction_note,
            'created_date' => $created?->toDateString(),
            'age_days' => $created ? max(0, (int) $created->copy()->startOfDay()->diffInDays($today, false)) : null,
            'is_demo' => $card->demo_key !== null, 'reference' => $card->demo_key ?? 'AF-'.$card->id,
            'next_actions' => array_map(fn ($next) => ['state' => $next, 'label' => self::ACTIONS[$next], 'requires_note' => in_array($next, ['documentation_corrections', 'development_corrections', 'cancelled'], true)], $actions),
            'owners' => collect(['documenter' => $card->documenter, 'developer' => $card->developer, 'tester' => $card->tester, 'approval' => $card->approvalOwner])
                ->map(fn ($user) => $user ? ['id' => $user->id, 'name' => $user->name] : null)->all(),
            'active_owner' => $card->workflowOwner()?->name,
            'active_owner_id' => $card->workflowOwner()?->id,
            'history' => $card->workflowEvents->map(fn ($event) => [
                'id' => $event->id, 'from_state' => $event->from_state, 'to_state' => $event->to_state,
                'label' => self::LABELS[$event->to_state] ?? $event->to_state, 'note' => $event->note,
                'created_at' => $event->created_at?->toIso8601String(),
            ]),
        ];
    }
}
