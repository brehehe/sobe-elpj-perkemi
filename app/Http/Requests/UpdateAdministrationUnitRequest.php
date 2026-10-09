<?php

namespace App\Http\Requests;

use App\Models\Unit;
use App\Services\UnitHierarchyService;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateAdministrationUnitRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $unit = $this->route('unit');

        return $unit instanceof Unit && ($this->user()?->can('update', $unit) ?? false);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var Unit $unit */
        $unit = $this->route('unit');

        return [
            'parent_id' => ['nullable', 'uuid', Rule::exists('units', 'id')->where('is_active', true)],
            'code' => ['required', 'string', 'max:50', 'regex:/^[A-Z0-9][A-Z0-9_-]*$/', Rule::unique(Unit::class, 'code')->ignore($unit)],
            'name' => ['required', 'string', 'max:150'],
            'type' => ['required', Rule::in(['PUSAT', 'PROVINSI', 'KOTA', 'KABUPATEN', 'DOJO', 'UNIT'])],
            'is_active' => ['required', 'boolean'],
        ];
    }

    /** @return array<int, callable(Validator): void> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            /** @var Unit $unit */
            $unit = $this->route('unit');
            $hierarchy = app(UnitHierarchyService::class);
            $parentId = $this->validated('parent_id');
            $parent = $parentId ? Unit::query()->find($parentId) : null;
            $parentChanged = $parentId !== $unit->parent_id;
            $typeChanged = $this->validated('type') !== $unit->type;

            if ($parentChanged && ! $hierarchy->parentIsManageable($this->user(), $parentId)) {
                $validator->errors()->add('parent_id', 'Unit induk berada di luar lingkup kewenangan Anda.');
            }

            if ($hierarchy->createsCycle($unit, $parentId)) {
                $validator->errors()->add('parent_id', 'Unit tidak dapat dipindahkan ke dirinya sendiri atau turunannya.');
            }

            if (($parentChanged || $typeChanged) && ! $hierarchy->parentTypeIsValid($this->validated('type'), $parent)) {
                $validator->errors()->add('parent_id', 'Tingkat unit induk tidak sesuai dengan struktur Pusat → Provinsi → Kota/Kabupaten → Dojo.');
            }

            if (! $this->boolean('is_active') && $unit->is_active) {
                if ($unit->users()->where('is_active', true)->exists()) {
                    $validator->errors()->add('is_active', 'Nonaktifkan atau pindahkan seluruh pengguna aktif sebelum menonaktifkan unit.');
                }

                if ($unit->children()->where('is_active', true)->exists()) {
                    $validator->errors()->add('is_active', 'Nonaktifkan atau pindahkan unit turunan aktif terlebih dahulu.');
                }
            }
        }];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'code.regex' => 'Kode hanya boleh berisi huruf kapital, angka, tanda hubung, dan garis bawah.',
            'code.unique' => 'Kode unit sudah digunakan.',
            'parent_id.exists' => 'Unit induk aktif tidak ditemukan.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'parent_id' => $this->string('parent_id')->trim()->toString() ?: null,
            'code' => $this->string('code')->trim()->upper()->toString(),
            'name' => $this->string('name')->squish()->toString(),
            'type' => $this->string('type')->trim()->upper()->toString(),
            'is_active' => $this->has('is_active') ? $this->boolean('is_active') : null,
        ]);
    }
}
