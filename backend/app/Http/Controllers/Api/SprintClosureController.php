<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\SprintResource;
use App\Models\Sprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SprintClosureController extends Controller
{
    public function update(Request $request, Sprint $sprint)
    {
        $today = now(config('sprints.timezone'))->toDateString();
        $data = $request->validate([
            'review_date' => 'sometimes|nullable|date_format:Y-m-d|before_or_equal:'.$today,
            'dev_review_date' => 'sometimes|nullable|date_format:Y-m-d|before_or_equal:'.$today,
            'dev_review_jira_url' => 'sometimes|nullable|url:http,https|max:2048',
            'production_release_date' => 'sometimes|nullable|date_format:Y-m-d|before_or_equal:'.$today,
        ]);
        $saved = DB::transaction(function () use ($sprint, $data) {
            DB::table('sprint_settings')->where('id', 1)->lockForUpdate()->first();
            $locked = Sprint::whereKey($sprint->id)->lockForUpdate()->firstOrFail();
            $locked->setRelation('cards', $locked->cards()->lockForUpdate()->get());
            if ($locked->production_release_date) {
                throw ValidationException::withMessages(['sprint' => 'El sprint ya fue finalizado. Sus datos de cierre se conservan en el historial.']);
            }
            if (!$locked->readyForClosure()) {
                throw ValidationException::withMessages(['sprint' => 'Todas las tarjetas deben estar aprobadas por testing para registrar el cierre.']);
            }
            foreach ($data as $key => $value) $locked->setAttribute($key, $value);
            $review = $locked->review_date?->toDateString();
            $dev = $locked->dev_review_date?->toDateString();
            $release = $locked->production_release_date?->toDateString();
            $errors = [];
            if ($review && $review < $locked->start_date->toDateString()) $errors['review_date'] = 'El review no puede ser anterior al inicio del sprint.';
            if ($dev && (!$review || $dev < $review)) $errors['dev_review_date'] = 'Registra primero el review; la revisión en desarrollo debe ser de esa fecha o posterior.';
            if ($locked->dev_review_jira_url && !$dev) $errors['dev_review_jira_url'] = 'Registra la fecha de revisión en desarrollo para asociar su tarjeta de Jira.';
            if ($release && (!$dev || !$review || $release < $dev)) $errors['production_release_date'] = 'Para finalizar, registra el review y la revisión en desarrollo. La liberación debe ser de esa fecha o posterior.';
            if ($errors) throw ValidationException::withMessages($errors);
            $locked->save();
            if ($release) {
                foreach ($locked->cards as $card) $locked->cards()->updateExistingPivot($card->id, ['is_completed' => true]);
            }
            return $locked->fresh();
        });
        return new SprintResource($saved);
    }
}
