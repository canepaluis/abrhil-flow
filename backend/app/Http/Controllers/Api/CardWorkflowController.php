<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Card;
use App\Models\User;
use App\Services\CardWorkflow;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class CardWorkflowController extends Controller
{
    public function update(Request $request, Card $card, CardWorkflow $workflow)
    {
        $data = $request->validate([
            'state' => ['sometimes', Rule::in(array_keys(CardWorkflow::LABELS))],
            'note' => 'nullable|string|max:5000',
            'documenter_id' => 'sometimes|nullable|integer|exists:users,id',
            'developer_id' => 'sometimes|nullable|integer|exists:users,id',
            'tester_id' => 'sometimes|nullable|integer|exists:users,id',
            'approval_owner_id' => 'sometimes|nullable|integer|exists:users,id',
            'requested_at' => 'sometimes|date_format:Y-m-d|before_or_equal:'.now(config('sprints.timezone'))->toDateString(),
        ]);
        foreach (['developer_id' => 'developer', 'tester_id' => 'tester'] as $field => $role) {
            if (!empty($data[$field]) && !User::whereKey($data[$field])->whereHas('roles', fn ($query) => $query->where('slug', $role))->exists()) {
                throw ValidationException::withMessages([$field => 'El usuario no tiene el rol requerido para esta fase.']);
            }
        }
        $card = DB::transaction(function () use ($card, $data, $workflow) {
            $locked = Card::whereKey($card->id)->lockForUpdate()->firstOrFail();
            $locked->fill(array_intersect_key($data, array_flip(['documenter_id', 'developer_id', 'tester_id', 'approval_owner_id', 'requested_at'])));
            if (isset($data['state'])) $workflow->move($locked, $data['state'], $data['note'] ?? null);
            $locked->save();
            return $locked;
        });
        return response()->json(['data' => ['id' => $card->id, 'workflow' => $workflow->describe($card)]]);
    }
}
