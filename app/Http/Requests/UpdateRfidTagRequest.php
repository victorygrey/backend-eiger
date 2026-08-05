<?php

namespace App\Http\Requests;

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
        $tagId = $this->route('rfid_tag')?->id;

        return [
            'uid'        => ['sometimes', 'required', 'string', 'max:100', Rule::unique('rfid_tags', 'uid')->ignore($tagId)],
            'product_id' => ['sometimes', 'required', 'integer', 'exists:products,id', Rule::unique('rfid_tags', 'product_id')->ignore($tagId)],
        ];
    }
}
