<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreRfidTagRequest extends FormRequest
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
        return [
            'uid'        => ['required', 'string', 'max:100', 'regex:/\A[0-9A-Fa-f]+\z/', 'unique:rfid_tags,uid'],
            'product_id' => ['nullable', 'integer', 'exists:products,id', 'unique:rfid_tags,product_id'],
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
