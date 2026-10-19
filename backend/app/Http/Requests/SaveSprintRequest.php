<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class SaveSprintRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'goal' => 'nullable|string|max:5000',
            'start_date' => 'required|date_format:Y-m-d',
            'end_date' => 'required|date_format:Y-m-d|after_or_equal:start_date|before_or_equal:'. $this->maxEndDate(),
            'members_count' => 'sometimes|integer|min:1|max:1000',
            'participant_ids' => 'required|array|min:1|max:1000',
            'participant_ids.*' => 'required|integer|distinct|exists:users,id',
            'daily_minutes' => 'required|integer|min:1|max:1440',
            'working_days' => 'required|array|min:1|max:7',
            'working_days.*' => 'required|integer|distinct|min:1|max:7',
            'cards' => 'present|array|max:500',
            'cards.*.id' => ['required', 'integer', 'distinct', Rule::exists('cards', 'id')->whereNull('deleted_at')],
            'cards.*.estimated_minutes' => 'required|integer|min:1|max:1000000',
            'cards.*.is_completed' => 'sometimes|boolean',
            'cards.*.start_date' => 'required|date_format:Y-m-d|after_or_equal:start_date|before_or_equal:end_date',
            'cards.*.end_date' => 'required|date_format:Y-m-d|after_or_equal:cards.*.start_date|before_or_equal:end_date',
        ];
    }

    private function maxEndDate(): string
    {
        try {
            return \Carbon\Carbon::createFromFormat('!Y-m-d', (string) $this->input('start_date'))->addYear()->toDateString();
        } catch (\Throwable) {
            return '9999-12-31';
        }
    }

    public function after(): array
    {
        return [function (Validator $validator) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }
            $days = array_map('intval', $this->input('working_days'));
            $hasWorkingDay = function ($start, $end) use ($days) {
                foreach (\Carbon\CarbonPeriod::create($start, $end) as $date) {
                    if (in_array($date->dayOfWeekIso, $days, true)) {
                        return true;
                    }
                }
                return false;
            };
            if (!$hasWorkingDay($this->input('start_date'), $this->input('end_date'))) {
                $validator->errors()->add('working_days', 'El sprint debe incluir al menos un día laborable.');
            }
            foreach ($this->input('cards') as $index => $card) {
                if (!$hasWorkingDay($card['start_date'], $card['end_date'])) {
                    $validator->errors()->add("cards.$index.end_date", 'La tarjeta debe incluir al menos un día laborable.');
                }
            }
        }];
    }
}
