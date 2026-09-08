<?php

namespace App\Http\Requests;

use App\Models\RfidTag;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateRfidTagRequest extends FormRequest
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
        $routeTag = $this->route('rfid_tag') ?? $this->route('rfidTag');
        $tagId = $routeTag instanceof RfidTag ? $routeTag->id : $routeTag;

        return [
            'uid'        => ['sometimes', 'required', 'string', 'max:100', 'regex:/\A[0-9A-Fa-f]+\z/', Rule::unique('rfid_tags', 'uid')->ignore($tagId)],
            'product_id' => ['sometimes', 'nullable', 'integer', 'exists:products,id', Rule::unique('rfid_tags', 'product_id')->ignore($tagId)],
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
