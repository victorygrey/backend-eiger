<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateZoneRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $zoneId = $this->route('zone')?->id;

        return [
            'name'        => ['sometimes', 'required', 'string', 'max:255', Rule::unique('zones', 'name')->ignore($zoneId)],
            'description' => ['nullable', 'string'],
        ];
    }
}
