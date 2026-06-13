<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreCardRequest extends FormRequest
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
            'title' => 'required|string',
            'description' => 'required|string',
            'team_id' => 'required|integer|exists:teams,id',
            'platform_id' => 'required|integer|exists:platforms,id',
            'project_id' => 'required|integer|exists:projects,id',
            'status_id' => 'required|integer|exists:statuses,id',
            'card_type_id' => 'required|integer|exists:card_types,id',
            'jira_link' => 'nullable|string',
            'priority' => 'nullable|integer',
            'assigned_to' => 'nullable|integer|exists:users,id',
            'module_id' => 'nullable|integer|exists:modules,id'
        ];
    }
}