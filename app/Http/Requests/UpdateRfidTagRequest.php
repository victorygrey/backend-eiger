<?php

namespace App\Http\Requests;

use App\Models\RfidTag;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateRfidTagRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $routeTag = $this->route('rfid_tag') ?? $this->route('rfidTag');
        $tagId = $routeTag instanceof RfidTag ? $routeTag->id : $routeTag;

        return [
            'uid' => ['sometimes', 'required', 'string', 'max:100', 'regex:/\A[0-9A-Fa-f]+\z/', Rule::unique('rfid_tags', 'uid')->ignore($tagId)],
            'name' => ['nullable', 'string', 'max:255'],
            'product_id' => ['nullable', 'integer', 'exists:products,id'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('uid')) {
            $this->merge([
                'uid' => strtoupper(str_replace(['-', ':', ' '], '', trim((string) $this->input('uid')))),
            ]);
        }
    }
}
