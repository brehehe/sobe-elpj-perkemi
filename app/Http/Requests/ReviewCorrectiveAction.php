<?php

namespace App\Http\Requests;

use App\Models\CorrectiveAction;
use App\Models\Finding;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ReviewCorrectiveAction extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $finding = $this->route('finding');
        $correctiveAction = $this->route('correctiveAction');

        return $finding instanceof Finding
            && $correctiveAction instanceof CorrectiveAction
            && $correctiveAction->finding_id === $finding->id
            && ($this->user()?->can('findings.manage') ?? false)
            && ($this->user()?->can('view', $finding) ?? false)
            && $correctiveAction->submitted_by !== $this->user()?->id;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'decision' => ['required', 'in:ACCEPTED,REJECTED'],
            'notes' => ['nullable', 'required_if:decision,REJECTED', 'string', 'max:3000'],
        ];
    }
}
