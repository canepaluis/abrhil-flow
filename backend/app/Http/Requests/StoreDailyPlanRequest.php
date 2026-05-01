<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreDailyPlanRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'date' => 'required|date',
            'activity_type_id' => 'required|integer|exists:activity_types,id',
            'assigned_to' => 'required|integer|exists:users,id',
            'card_id' => 'nullable|integer',
            'estimated_minutes' => 'integer',
            'real_minutes' => 'integer'
        ];
    }
}
