<?php

namespace App\Http\Requests;

use App\Enums\GovernancePolicyType;
use App\Models\ActivityTaxonomy;
use App\Models\GovernancePolicy;
use App\Services\GovernancePolicyRegistry;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class PublishGovernancePolicyRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('create', GovernancePolicy::class) ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<mixed>|string>
     */
    public function rules(): array
    {
        $type = $this->policyType();
        $common = [
            'version' => [
                'required',
                'string',
                'max:100',
                'regex:/^[a-z0-9][a-z0-9._-]*$/',
                Rule::unique('governance_policies', 'version')->where('type', $type?->value),
            ],
            'change_summary' => ['required', 'string', 'min:10', 'max:1000'],
        ];

        return [...$common, ...match ($type) {
            GovernancePolicyType::Verification => [
                'clarification_due_days' => ['required', 'integer', 'between:1,30'],
                'administration_checklist' => ['required', 'string', 'max:5000'],
                'finance_checklist' => ['required', 'string', 'max:5000'],
                'substantive_checklist' => ['required', 'string', 'max:5000'],
            ],
            GovernancePolicyType::Workflow => [
                'verification_due_days' => ['required', 'integer', 'between:1,30'],
                'approval_due_days' => ['required', 'integer', 'between:1,30'],
                'pb_verification_due_days' => ['required', 'integer', 'between:1,30'],
                'archive_due_days' => ['required', 'integer', 'between:1,30'],
                'revision_due_days' => ['required', 'integer', 'between:1,30'],
                'escalation_lead_hours' => ['required', 'integer', 'between:1,168'],
            ],
            GovernancePolicyType::Templates => [
                'default_label' => ['required', 'string', 'max:120'],
                'template_mappings' => ['required', 'string', 'max:5000'],
                'sections' => ['required', 'string', 'max:3000'],
            ],
            GovernancePolicyType::Retention => [
                'retention_years' => ['required', 'integer', 'between:1,50'],
                'schema_version' => ['required', 'integer', 'between:1,100'],
                'disk' => ['required', 'string', Rule::in(array_keys(config('filesystems.disks', [])))],
            ],
            GovernancePolicyType::Ai => [
                'external_provider_enabled' => ['required', 'boolean'],
                'daily_token_budget' => ['required', 'integer', 'between:0,10000000'],
                'monthly_token_budget' => ['required', 'integer', 'between:0,300000000'],
                'warning_percent' => ['required', 'integer', 'between:1,99'],
                'user_rate_per_minute' => ['required', 'integer', 'between:1,100'],
                'unit_rate_per_minute' => ['required', 'integer', 'between:1,1000'],
                'organization_rate_per_minute' => ['required', 'integer', 'between:1,10000'],
                'review_disclaimer' => ['required', 'string', 'max:500'],
            ],
            GovernancePolicyType::System => [
                'data_classification' => ['required', 'string', 'max:120'],
                'maintenance_message' => ['nullable', 'string', 'max:500'],
                'support_message' => ['required', 'string', 'max:500'],
            ],
            null => [],
        }];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $type = $this->policyType();
            if ($type === null) {
                $validator->errors()->add('type', 'Jenis kebijakan tidak dikenali.');

                return;
            }

            $active = app(GovernancePolicyRegistry::class)->get($type);
            if ($this->string('version')->toString() === ($active['version'] ?? null)) {
                $validator->errors()->add('version', 'Versi baru harus berbeda dari versi aktif.');
            }

            if ($type === GovernancePolicyType::Verification) {
                foreach (['administration_checklist', 'finance_checklist', 'substantive_checklist'] as $field) {
                    $lines = $this->lines($this->input($field));
                    if ($lines === [] || count($lines) > 20) {
                        $validator->errors()->add($field, 'Checklist wajib berisi 1 sampai 20 baris.');
                    }
                }
            }

            if ($type === GovernancePolicyType::Templates) {
                $mappings = $this->mappingLines($this->input('template_mappings'));
                $mappingLines = $this->lines($this->input('template_mappings'));
                $validCodes = ActivityTaxonomy::query()->where('is_active', true)->pluck('code')->all();
                if ($mappings === [] || count($mappings) !== count($mappingLines) || collect($mappings)->pluck('activity_type')->duplicates()->isNotEmpty()) {
                    $validator->errors()->add('template_mappings', 'Pemetaan wajib unik dan menggunakan format KODE=Nama template.');
                }
                foreach ($mappings as $mapping) {
                    if (! in_array($mapping['activity_type'], $validCodes, true)) {
                        $validator->errors()->add('template_mappings', 'Kode taksonomi '.$mapping['activity_type'].' tidak aktif atau tidak tersedia.');
                    }
                }
                $sections = $this->lines($this->input('sections'));
                if (count($sections) < 3 || count($sections) > 20) {
                    $validator->errors()->add('sections', 'Bagian template wajib berisi 3 sampai 20 baris.');
                }
            }
        });
    }

    /** @return array<string, mixed> */
    public function policySettings(): array
    {
        $type = $this->policyType();

        return match ($type) {
            GovernancePolicyType::Verification => [
                'clarification_due_days' => $this->integer('clarification_due_days'),
                'stages' => [
                    'SYSTEM' => ['label' => 'System check', 'checklist' => []],
                    'ADMINISTRATION' => ['label' => 'Administrasi', 'checklist' => $this->lines($this->input('administration_checklist'))],
                    'FINANCE' => ['label' => 'Keuangan', 'checklist' => $this->lines($this->input('finance_checklist'))],
                    'SUBSTANTIVE' => ['label' => 'Substantif/kepatuhan', 'checklist' => $this->lines($this->input('substantive_checklist'))],
                ],
            ],
            GovernancePolicyType::Workflow => collect($this->validated())->only([
                'verification_due_days', 'approval_due_days', 'pb_verification_due_days',
                'archive_due_days', 'revision_due_days', 'escalation_lead_hours',
            ])->map(fn (mixed $value): int => (int) $value)->all(),
            GovernancePolicyType::Templates => [
                'default_label' => $this->string('default_label')->trim()->toString(),
                'mappings' => $this->mappingLines($this->input('template_mappings')),
                'sections' => $this->lines($this->input('sections')),
            ],
            GovernancePolicyType::Retention => [
                'retention_years' => $this->integer('retention_years'),
                'schema_version' => $this->integer('schema_version'),
                'disk' => $this->string('disk')->toString(),
            ],
            GovernancePolicyType::Ai => [
                'external_provider_enabled' => $this->boolean('external_provider_enabled'),
                'daily_token_budget' => $this->integer('daily_token_budget'),
                'monthly_token_budget' => $this->integer('monthly_token_budget'),
                'warning_percent' => $this->integer('warning_percent'),
                'user_rate_per_minute' => $this->integer('user_rate_per_minute'),
                'unit_rate_per_minute' => $this->integer('unit_rate_per_minute'),
                'organization_rate_per_minute' => $this->integer('organization_rate_per_minute'),
                'review_disclaimer' => $this->string('review_disclaimer')->trim()->toString(),
            ],
            GovernancePolicyType::System => [
                'data_classification' => $this->string('data_classification')->trim()->toString(),
                'maintenance_message' => $this->filled('maintenance_message') ? $this->string('maintenance_message')->trim()->toString() : null,
                'support_message' => $this->string('support_message')->trim()->toString(),
            ],
            null => [],
        };
    }

    public function policyType(): ?GovernancePolicyType
    {
        return GovernancePolicyType::tryFrom(mb_strtoupper((string) $this->route('type')));
    }

    /** @return list<string> */
    private function lines(mixed $value): array
    {
        return collect(preg_split('/\R/u', (string) $value) ?: [])
            ->map(fn (string $line): string => trim($line))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    /** @return list<array{activity_type: string, label: string}> */
    private function mappingLines(mixed $value): array
    {
        return collect($this->lines($value))->map(function (string $line): ?array {
            $parts = explode('=', $line, 2);
            if (count($parts) !== 2 || blank(trim($parts[0])) || blank(trim($parts[1]))) {
                return null;
            }

            return ['activity_type' => mb_strtoupper(trim($parts[0])), 'label' => trim($parts[1])];
        })->filter()->values()->all();
    }
}
