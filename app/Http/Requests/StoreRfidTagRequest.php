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
            'uid'        => ['required', 'string', 'max:100', 'unique:rfid_tags,uid'],
            'product_id' => ['required', 'integer', 'exists:products,id', 'unique:rfid_tags,product_id'],
        ];
    }
}
