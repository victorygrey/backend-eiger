<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProductRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        // Ignore the current product's own SKU when checking uniqueness
        $productId = $this->route('product')?->id;

        return [
            'sku'             => ['sometimes', 'required', 'string', 'max:100', Rule::unique('products', 'sku')->ignore($productId)],
            'name'            => ['sometimes', 'required', 'string', 'max:255'],
            'price'           => ['nullable', 'numeric', 'min:0'],
            'stock'           => ['nullable', 'integer', 'min:0'],
            'zone_id'         => ['nullable', 'integer', 'exists:zones,id'],
            'image'           => ['nullable', 'string', 'max:8192'],
            'material'        => ['nullable', 'string', 'max:100'],
            'description'     => ['nullable', 'string'],
            'is_featured'     => ['nullable', 'boolean'],
            'is_discontinued' => ['nullable', 'boolean'],
        ];
    }
}
