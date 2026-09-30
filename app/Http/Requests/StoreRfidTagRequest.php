<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreRfidTagRequest extends FormRequest
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
        return [
            'uid' => ['required', 'string', 'max:100', 'regex:/\A[0-9A-Fa-f]+\z/', 'unique:rfid_tags,uid'],
            'name' => ['nullable', 'string', 'max:255'],
            'product_id' => ['nullable', 'integer', 'exists:products,id'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('uid')) {
            $this->merge(['uid' => $this->canonicalUid($this->input('uid'))]);
        }
    }

    private function canonicalUid(mixed $uid): string
    {
        return strtoupper(str_replace(['-', ':', ' '], '', trim((string) $uid)));
    }
}
