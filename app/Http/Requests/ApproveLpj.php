<?php

namespace App\Http\Requests;

use App\Enums\ApprovalDecision;
use App\Enums\ApprovalLevel;
use App\Models\Lpj;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ApproveLpj extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $lpj = $this->route('lpj');

        if (! $lpj instanceof Lpj) {
            return false;
        }

        return $this->string('level')->toString() === ApprovalLevel::Pb->value
            ? ($this->user()?->can('pbVerify', $lpj) ?? false)
            : ($this->user()?->can('approve', $lpj) ?? false);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'level' => ['required', Rule::enum(ApprovalLevel::class)],
            'decision' => ['required', Rule::enum(ApprovalDecision::class)],
            'notes' => ['nullable', 'required_unless:decision,APPROVED', 'string', 'max:3000'],
        ];
    }
}
